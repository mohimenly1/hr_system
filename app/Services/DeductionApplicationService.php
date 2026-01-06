<?php

namespace App\Services;

use App\Models\DeductionRule;
use App\Models\Contract;
use App\Models\TeacherContract;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DeductionApplicationService
{
    /**
     * Apply deduction rules to attendance comparison data
     */
    public function applyDeductionRules(array $comparisonData, $personId, string $personType, Carbon $startDate, Carbon $endDate): array
    {
        // Get active deduction rules ordered by priority
        $rules = DeductionRule::where('is_active', true)
            ->with('penaltyType')
            ->orderBy('priority', 'desc')
            ->get();

        \Log::info('DeductionApplicationService: Applying rules', [
            'person_id' => $personId,
            'person_type' => $personType,
            'rules_count' => $rules->count(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $appliedDeductions = [];
        $notAppliedRules = [];
        $totalDeduction = 0;

        foreach ($rules as $rule) {
            \Log::info('DeductionApplicationService: Evaluating rule', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'deduction_type' => $rule->effective_deduction_type,
                'deduction_amount' => $rule->effective_deduction_amount,
            ]);

            $evaluationResult = $this->evaluateRuleConditionsWithDetails($rule, $comparisonData, $startDate, $endDate);

            \Log::info('DeductionApplicationService: Rule evaluation result', [
                'rule_id' => $rule->id,
                'applies' => $evaluationResult['applies'],
                'reason' => $evaluationResult['reason'] ?? null,
                'triggered_days_count' => count($evaluationResult['triggered_days'] ?? []),
            ]);

            if ($evaluationResult['applies']) {
                // Check if this is a non-consecutive or consecutive rule with groups
                $hasGroups = isset($evaluationResult['valid_groups']) && !empty($evaluationResult['valid_groups']);
                $validGroups = $evaluationResult['valid_groups'] ?? [];
                
                \Log::info('DeductionApplicationService: Checking for groups', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'deduction_type' => $rule->effective_deduction_type,
                    'has_groups' => $hasGroups,
                    'valid_groups_count' => count($validGroups),
                    'evaluation_result_keys' => array_keys($evaluationResult),
                ]);
                
                if ($hasGroups && !empty($validGroups)) {
                    // Process each group separately (each group = 3 non-consecutive days)
                    $groupDeductions = [];
                    $totalGroupDeduction = 0;
                    
                    foreach ($validGroups as $groupIndex => $group) {
                        $groupDaysCount = count($group);
                        
                        // For daily_salary type, calculate deduction based on deduction_days (not groupDaysCount)
                        // Each group of consecutive days gets one full day deduction
                        if ($rule->effective_deduction_type === 'daily_salary') {
                            // Calculate one full day deduction per group
                            // IMPORTANT: For consecutive groups, each group (of 3 days) = 1 day deduction
                            // Ignore deduction_days from database, always use 1 day per group
                            $salary = $this->getPersonSalary($personId, $personType);
                            $workingDaysPerMonth = $this->getWorkingDaysPerMonth($personId, $personType, $startDate, $endDate);
                            $dailySalary = $workingDaysPerMonth > 0 ? $salary / $workingDaysPerMonth : 0;
                            // Always use 1 day deduction per group, regardless of deduction_days in database
                            $groupDeductionAmount = $dailySalary * 1; // One day per group
                        } else {
                            // For other types (percentage), calculate based on groupDaysCount
                            $groupDeductionAmount = $this->calculateDeductionAmount(
                                $rule,
                                $personId,
                                $personType,
                                $comparisonData,
                                $groupDaysCount,
                                $startDate,
                                $endDate
                            );
                        }
                        
                        // Calculate amount per day for this group
                        // For daily_salary type, amount_per_day is the full daily salary (one day per group of 3)
                        // For other types, divide by groupDaysCount
                        if ($rule->effective_deduction_type === 'daily_salary') {
                            $groupAmountPerDay = $groupDeductionAmount; // Full day deduction per group
                        } else {
                            $groupAmountPerDay = $groupDaysCount > 0 ? $groupDeductionAmount / $groupDaysCount : 0;
                        }
                        
                        $groupDeductions[] = [
                            'group_number' => $groupIndex + 1,
                            'days' => $group,
                            'days_count' => $groupDaysCount,
                            'deduction_amount' => round($groupDeductionAmount, 2),
                            'amount_per_day' => round($groupAmountPerDay, 2), // Add amount per day for display
                        ];
                        
                        $totalGroupDeduction += $groupDeductionAmount;
                    }
                    
                    \Log::info('DeductionApplicationService: Calculated deduction amount for groups', [
                        'rule_id' => $rule->id,
                        'total_groups' => count($validGroups),
                        'total_deduction_amount' => $totalGroupDeduction,
                        'group_deductions' => $groupDeductions,
                    ]);
                    
                    if ($totalGroupDeduction > 0) {
                        $appliedDeductions[] = [
                            'rule' => [
                                'id' => $rule->id,
                                'name' => $rule->name,
                                'description' => $rule->description,
                                'priority' => $rule->priority,
                            ],
                            'deduction_type' => $rule->effective_deduction_type,
                            'deduction_amount' => round($totalGroupDeduction, 2),
                            'applied_conditions' => $this->getAppliedConditionsSummary($rule, $comparisonData),
                            'triggered_days' => $evaluationResult['triggered_days'],
                            'triggered_count' => count($evaluationResult['triggered_days']),
                            'reason' => $evaluationResult['reason'],
                            'groups' => $groupDeductions,
                            'total_groups' => count($validGroups),
                        ];

                        $totalDeduction += $totalGroupDeduction;
                    }
                } else {
                    // Original logic for non-grouped deductions
                    $triggeredDaysCount = count($evaluationResult['triggered_days']);
                    
                    // For percentage type deductions, ensure we have at least 1 day if rule applies
                    // This handles cases where rule applies but triggered_days array might be empty
                    if ($triggeredDaysCount === 0 && $rule->effective_deduction_type === 'percentage') {
                        $triggeredDaysCount = 1;
                    }
                    
                    $deductionAmount = $this->calculateDeductionAmount($rule, $personId, $personType, $comparisonData, $triggeredDaysCount, $startDate, $endDate);
                    
                    // Calculate amount per day for display purposes
                    // For daily_salary type with consecutive days, amount_per_day should be the full daily salary
                    // (not divided by triggered days, because each group of 3 consecutive days = 1 full day deduction)
                    $amountPerDay = 0;
                    if ($rule->effective_deduction_type === 'daily_salary') {
                        // For daily_salary, amount_per_day is the full daily salary (one day per group)
                        $salary = $this->getPersonSalary($personId, $personType);
                        $workingDaysPerMonth = $this->getWorkingDaysPerMonth($personId, $personType, $startDate, $endDate);
                        $amountPerDay = $workingDaysPerMonth > 0 ? $salary / $workingDaysPerMonth : 0;
                    } elseif ($triggeredDaysCount > 0) {
                        $amountPerDay = $deductionAmount / $triggeredDaysCount;
                    }

                    \Log::info('DeductionApplicationService: Calculated deduction amount', [
                        'rule_id' => $rule->id,
                        'deduction_amount' => $deductionAmount,
                        'triggered_days_count' => $triggeredDaysCount,
                        'amount_per_day' => $amountPerDay,
                    ]);

                    if ($deductionAmount > 0) {
                        $appliedDeductions[] = [
                            'rule' => [
                                'id' => $rule->id,
                                'name' => $rule->name,
                                'description' => $rule->description,
                                'priority' => $rule->priority,
                            ],
                            'deduction_type' => $rule->effective_deduction_type,
                            'deduction_amount' => round($deductionAmount, 2),
                            'amount_per_day' => round($amountPerDay, 2), // Add amount per day for display
                            'applied_conditions' => $this->getAppliedConditionsSummary($rule, $comparisonData),
                            'triggered_days' => $evaluationResult['triggered_days'],
                            'triggered_count' => $triggeredDaysCount,
                            'reason' => $evaluationResult['reason'],
                            'groups' => null,
                            'total_groups' => 0,
                        ];

                        $totalDeduction += $deductionAmount;
                    }
                }
            } else {
                $notAppliedRules[] = [
                    'rule' => [
                        'id' => $rule->id,
                        'name' => $rule->name,
                        'description' => $rule->description,
                    ],
                    'reason' => $evaluationResult['reason'],
                    'found_events' => $evaluationResult['found_events'] ?? 0,
                    'required_events' => $evaluationResult['required_events'] ?? 0,
                    'total_found' => $evaluationResult['total_found'] ?? $evaluationResult['found_events'] ?? 0,
                    'filtered_out_reason' => $evaluationResult['filtered_out_reason'] ?? null,
                ];
            }
        }

        return [
            'applied_deductions' => $appliedDeductions,
            'not_applied_rules' => $notAppliedRules,
            'total_deduction' => round($totalDeduction, 2),
        ];
    }

    /**
     * Evaluate if a deduction rule's conditions are met
     */
    private function evaluateRuleConditions(DeductionRule $rule, array $comparisonData, Carbon $startDate, Carbon $endDate): bool
    {
        $result = $this->evaluateRuleConditionsWithDetails($rule, $comparisonData, $startDate, $endDate);
        return $result['applies'];
    }

    /**
     * Evaluate rule conditions with detailed information
     */
    private function evaluateRuleConditionsWithDetails(DeductionRule $rule, array $comparisonData, Carbon $startDate, Carbon $endDate): array
    {
        $conditions = $rule->conditions;

        \Log::info('DeductionApplicationService: evaluateRuleConditionsWithDetails called', [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'has_conditions' => !empty($conditions),
            'conditions_keys' => $conditions ? array_keys($conditions) : [],
            'conditions' => $conditions,
        ]);

        if (!$conditions || !isset($conditions['event_type'])) {
            return [
                'applies' => false,
                'reason' => 'لا توجد شروط محددة للقاعدة',
                'triggered_days' => [],
            ];
        }

        $eventType = $conditions['event_type'];
        $comparisons = collect($comparisonData['comparisons']);

        // Filter comparisons based on event type
        $filterResult = $this->filterComparisonsByEventTypeWithDetails($comparisons, $eventType, $conditions);
        $relevantComparisons = $filterResult['filtered'];
        $totalEvents = $filterResult['total_events'];
        $filteredOutReason = $filterResult['filtered_out_reason'];

        if ($relevantComparisons->isEmpty()) {
            $reason = 'لم يتم العثور على أي أحداث مطابقة لنوع الحدث المحدد';

            if ($totalEvents > 0 && $filteredOutReason) {
                $reason .= ' (' . $filteredOutReason . ')';
            }

            return [
                'applies' => false,
                'reason' => $reason,
                'triggered_days' => [],
                'found_events' => 0,
                'required_events' => 1,
                'total_events_found' => $totalEvents,
                'filtered_out_reason' => $filteredOutReason,
            ];
        }

        // Check occurrence type and count
        // IMPORTANT: Check occurrence_type first, even if occurrence_count is null
        if (isset($conditions['occurrence_type'])) {
            // For consecutive + daily_salary type, occurrence_count might be null or 1 (meaning 1 group)
            // but we need to group by 3 days per group. So we use 3 as the group size.
            $requiredCount = $conditions['occurrence_count'] ?? 3; // Default to 3 if not set
            if ($conditions['occurrence_type'] === 'consecutive' && $rule->effective_deduction_type === 'daily_salary') {
                // For "3 consecutive days = 1 full day deduction", we need groups of 3
                // occurrence_count = 1 means "1 group", but group size should be 3
                $requiredCount = 3; // Always use 3 days per group for consecutive daily_salary deductions
            } elseif ($conditions['occurrence_type'] === 'consecutive' && !isset($conditions['occurrence_count'])) {
                // If occurrence_type is consecutive but occurrence_count is not set, default to 3
                $requiredCount = 3;
            }
            
            \Log::info('DeductionApplicationService: evaluateRuleConditionsWithDetails - calling checkOccurrenceConditionWithDetails', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'occurrence_type' => $conditions['occurrence_type'],
                'original_required_count' => $conditions['occurrence_count'],
                'adjusted_required_count' => $requiredCount,
                'deduction_type' => $rule->effective_deduction_type,
                'relevant_comparisons_count' => $relevantComparisons->count(),
            ]);
            
            // IMPORTANT: If occurrence_type is 'consecutive', we MUST only process consecutive days
            // If occurrence_type is 'non_consecutive', we MUST only process non-consecutive days
            if ($conditions['occurrence_type'] === 'consecutive') {
                \Log::info('DeductionApplicationService: Processing CONSECUTIVE rule', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'required_count' => $requiredCount,
                ]);
            } elseif ($conditions['occurrence_type'] === 'non_consecutive') {
                \Log::info('DeductionApplicationService: Processing NON-CONSECUTIVE rule', [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'required_count' => $requiredCount,
                ]);
            }
            
            $occurrenceResult = $this->checkOccurrenceConditionWithDetails(
                $relevantComparisons,
                $conditions['occurrence_type'],
                $requiredCount,
                $conditions['time_period'] ?? null,
                $startDate,
                $endDate
            );

            \Log::info('DeductionApplicationService: evaluateRuleConditionsWithDetails - occurrenceResult', [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'occurrence_type' => $conditions['occurrence_type'],
                'applies' => $occurrenceResult['applies'],
                'has_valid_groups' => isset($occurrenceResult['valid_groups']),
                'valid_groups_count' => count($occurrenceResult['valid_groups'] ?? []),
                'triggered_days_count' => count($occurrenceResult['triggered_days'] ?? []),
                'triggered_days_sample' => array_slice($occurrenceResult['triggered_days'] ?? [], 0, 5),
                'occurrence_result_keys' => array_keys($occurrenceResult),
            ]);

            return [
                'applies' => $occurrenceResult['applies'],
                'reason' => $occurrenceResult['reason'],
                'triggered_days' => $occurrenceResult['triggered_days'],
                'found_events' => $occurrenceResult['found_count'],
                'required_events' => $requiredCount,
                'total_found' => $occurrenceResult['total_found'] ?? $occurrenceResult['found_count'],
                'valid_groups' => $occurrenceResult['valid_groups'] ?? [], // Pass valid_groups from occurrenceResult
            ];
        }

        // If no occurrence condition, check if any relevant event exists
        $triggeredDays = $relevantComparisons->map(function ($comp) {
            return [
                'date' => $comp['date'],
                'day_name' => $comp['day_name'],
                'details' => $this->getEventDetails($comp),
            ];
        })->values()->toArray();

        return [
            'applies' => true,
            'reason' => 'تم العثور على ' . count($triggeredDays) . ' حدث مطابق',
            'triggered_days' => $triggeredDays,
            'found_events' => count($triggeredDays),
            'required_events' => 1,
        ];
    }

    /**
     * Get event details for a comparison
     */
    private function getEventDetails($comparison): string
    {
        $result = $comparison['comparison_result'];
        $details = [];

        if ($result['is_late']) {
            $minutesLate = round($result['minutes_late']);
            $details[] = 'تأخير: ' . $this->formatMinutesToHours($minutesLate);
        }
        if ($result['is_early_leave']) {
            $minutesEarly = round($result['minutes_early_leave']);
            $details[] = 'انصراف مبكر: ' . $this->formatMinutesToHours($minutesEarly);
        }
        if ($result['status'] === 'absent') {
            $details[] = 'غياب';
        }

        return implode('، ', $details) ?: 'حدث غير محدد';
    }

    /**
     * Format minutes to hours and minutes format
     * Example: 530 minutes = "8 ساعة و 50 دقيقة"
     */
    private function formatMinutesToHours(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' دقيقة';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . ' ساعة';
        }

        return $hours . ' ساعة و ' . $remainingMinutes . ' دقيقة';
    }

    /**
     * Filter comparisons based on event type
     */
    private function filterComparisonsByEventType(Collection $comparisons, string $eventType, array $conditions): Collection
    {
        $result = $this->filterComparisonsByEventTypeWithDetails($comparisons, $eventType, $conditions);
        return $result['filtered'];
    }

    /**
     * Filter comparisons based on event type with detailed information
     */
    private function filterComparisonsByEventTypeWithDetails(Collection $comparisons, string $eventType, array $conditions): array
    {
        $totalEvents = 0;
        $filteredOutCount = 0;
        $filteredOutReasons = [];

        $filtered = $comparisons->filter(function ($comparison) use ($eventType, $conditions, &$totalEvents, &$filteredOutCount, &$filteredOutReasons) {
            if ($comparison['is_weekend']) {
                return false;
            }

            $result = $comparison['comparison_result'];

            switch ($eventType) {
                case 'late':
                    if (!$result['is_late']) {
                        return false;
                    }
                    $totalEvents++;
                    $minutesLate = $result['minutes_late'] ?? 0;

                    // Check minutes late range if specified
                    if (isset($conditions['min_minutes_late']) && $minutesLate < $conditions['min_minutes_late']) {
                        $filteredOutCount++;
                        $filteredOutReasons[] = "تأخير {$minutesLate} دقيقة أقل من الحد الأدنى ({$conditions['min_minutes_late']} دقيقة)";
                        return false;
                    }
                    if (isset($conditions['max_minutes_late']) && $minutesLate > $conditions['max_minutes_late']) {
                        $filteredOutCount++;
                        $filteredOutReasons[] = "تأخير {$minutesLate} دقيقة أكبر من الحد الأقصى ({$conditions['max_minutes_late']} دقيقة)";
                        return false;
                    }
                    return true;

                case 'absent':
                    if ($result['status'] === 'absent') {
                        $totalEvents++;
                        return true;
                    }
                    return false;

                case 'absent_without_permission':
                    // This requires checking if there's a leave record - simplified for now
                    if ($result['status'] === 'absent') {
                        $totalEvents++;
                        return true;
                    }
                    return false;

                case 'early_leave':
                    if ($result['is_early_leave'] ?? false) {
                        $totalEvents++;
                        return true;
                    }
                    return false;

                case 'administrative_violation':
                case 'misconduct':
                case 'policy_violation':
                case 'dress_code_violation':
                case 'workplace_violation':
                case 'other':
                    // These would need to be checked against penalty records
                    // For now, return false as they require manual penalty entry
                    return false;

                default:
                    return false;
            }
        });

        $filteredOutReason = null;
        if ($filteredOutCount > 0 && !empty($filteredOutReasons)) {
            $uniqueReasons = array_unique($filteredOutReasons);
            $filteredOutReason = 'تم استبعاد ' . $filteredOutCount . ' حدث: ' . implode('، ', array_slice($uniqueReasons, 0, 3));
            if (count($uniqueReasons) > 3) {
                $filteredOutReason .= '...';
            }
        }

        return [
            'filtered' => $filtered,
            'total_events' => $totalEvents,
            'filtered_out_count' => $filteredOutCount,
            'filtered_out_reason' => $filteredOutReason,
        ];
    }

    /**
     * Check occurrence condition (consecutive, non-consecutive, total)
     */
    private function checkOccurrenceCondition(
        Collection $comparisons,
        string $occurrenceType,
        int $requiredCount,
        ?string $timePeriod,
        Carbon $startDate,
        Carbon $endDate
    ): bool {
        $result = $this->checkOccurrenceConditionWithDetails($comparisons, $occurrenceType, $requiredCount, $timePeriod, $startDate, $endDate);
        return $result['applies'];
    }

    /**
     * Check occurrence condition with detailed information
     */
    private function checkOccurrenceConditionWithDetails(
        Collection $comparisons,
        string $occurrenceType,
        int $requiredCount,
        ?string $timePeriod,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        \Log::info('DeductionApplicationService: checkOccurrenceConditionWithDetails called', [
            'occurrence_type' => $occurrenceType,
            'required_count' => $requiredCount,
            'comparisons_count' => $comparisons->count(),
        ]);
        
        // Filter by time period if specified
        $filteredComparisons = $this->filterByTimePeriod($comparisons, $timePeriod, $startDate, $endDate);
        $foundCount = $filteredComparisons->count();

        switch ($occurrenceType) {
            case 'consecutive':
                $consecutiveResult = $this->hasConsecutiveOccurrencesWithDetails($filteredComparisons, $requiredCount);
                
                \Log::info('DeductionApplicationService: Consecutive result', [
                    'required_count' => $requiredCount,
                    'applies' => $consecutiveResult['applies'],
                    'triggered_days_count' => count($consecutiveResult['triggered_days'] ?? []),
                    'max_consecutive' => $consecutiveResult['max_consecutive'] ?? 0,
                    'all_sequences_count' => count($consecutiveResult['all_sequences'] ?? []),
                    'triggered_days' => array_slice($consecutiveResult['triggered_days'] ?? [], 0, 5), // First 5 for logging
                ]);
                
                // For consecutive occurrences, group them into groups of requiredCount (e.g., groups of 3)
                $validGroups = [];
                $triggeredDaysFromGroups = [];
                
                if ($consecutiveResult['applies'] && !empty($consecutiveResult['all_sequences'])) {
                    // Process each consecutive sequence separately
                    foreach ($consecutiveResult['all_sequences'] as $sequence) {
                        // Group this sequence into groups of requiredCount
                        $sequenceGroups = $this->groupConsecutiveDaysFromSequence($sequence, $requiredCount);
                        
                        foreach ($sequenceGroups as $group) {
                            $validGroups[] = $group;
                            // Add days from this group to triggered_days
                            foreach ($group as $day) {
                                $triggeredDaysFromGroups[] = $day;
                            }
                        }
                    }
                    
                    \Log::info('DeductionApplicationService: Grouped consecutive days', [
                        'required_count' => $requiredCount,
                        'sequences_count' => count($consecutiveResult['all_sequences']),
                        'valid_groups_count' => count($validGroups),
                        'triggered_days_from_groups_count' => count($triggeredDaysFromGroups),
                        'valid_groups' => array_map(function($group) {
                            return [
                                'days_count' => count($group),
                                'days' => array_column($group, 'date'),
                            ];
                        }, $validGroups),
                    ]);
                } else {
                    \Log::warning('DeductionApplicationService: Cannot group consecutive days', [
                        'applies' => $consecutiveResult['applies'] ?? false,
                        'has_all_sequences' => !empty($consecutiveResult['all_sequences'] ?? []),
                    ]);
                }
                
                return [
                    'applies' => !empty($validGroups),
                    'reason' => !empty($validGroups)
                        ? "تم العثور على " . count($triggeredDaysFromGroups) . " يوم متتالي، تم تجميعها في " . count($validGroups) . " مجموعة (كل مجموعة {$requiredCount} أيام متتالية)"
                        : $consecutiveResult['reason'],
                    'triggered_days' => $triggeredDaysFromGroups, // Only days from valid groups
                    'found_count' => $consecutiveResult['max_consecutive'] ?? 0, // Use consecutive count, not total count
                    'total_found' => $foundCount, // Keep total for reference
                    'valid_groups' => $validGroups, // Add groups for consecutive deductions
                ];

            case 'non_consecutive':
                $nonConsecutiveResult = $this->hasNonConsecutiveOccurrencesWithDetails($filteredComparisons, $requiredCount);
                return [
                    'applies' => $nonConsecutiveResult['applies'],
                    'reason' => $nonConsecutiveResult['reason'],
                    'triggered_days' => $nonConsecutiveResult['triggered_days'],
                    'found_count' => $foundCount,
                ];

            case 'total':
                $triggeredDays = $filteredComparisons->map(function ($comp) {
                    return [
                        'date' => $comp['date'],
                        'day_name' => $comp['day_name'],
                        'details' => $this->getEventDetails($comp),
                    ];
                })->values()->toArray();

                return [
                    'applies' => $foundCount >= $requiredCount,
                    'reason' => $foundCount >= $requiredCount
                        ? "تم العثور على {$foundCount} حدث (المطلوب: {$requiredCount})"
                        : "تم العثور على {$foundCount} حدث فقط (المطلوب: {$requiredCount})",
                    'triggered_days' => $triggeredDays,
                    'found_count' => $foundCount,
                ];

            default:
                return [
                    'applies' => false,
                    'reason' => 'نوع التكرار غير معروف',
                    'triggered_days' => [],
                    'found_count' => $foundCount,
                    'total_found' => $foundCount,
                ];
        }
    }

    /**
     * Check for consecutive occurrences
     */
    private function hasConsecutiveOccurrences(Collection $comparisons, int $requiredCount): bool
    {
        $result = $this->hasConsecutiveOccurrencesWithDetails($comparisons, $requiredCount);
        return $result['applies'];
    }

    /**
     * Check for consecutive occurrences with details
     */
    private function hasConsecutiveOccurrencesWithDetails(Collection $comparisons, int $requiredCount): array
    {
        $sortedComparisons = $comparisons->sortBy('date')->values();

        if ($sortedComparisons->count() < $requiredCount) {
            return [
                'applies' => false,
                'reason' => "تم العثور على {$sortedComparisons->count()} حدث فقط (المطلوب: {$requiredCount} متتالية)",
                'triggered_days' => [],
                'max_consecutive' => 0,
                'total_found' => $sortedComparisons->count(),
                'all_sequences' => [], // No sequences found
            ];
        }

        // Find all consecutive sequences
        $allConsecutiveSequences = [];
        $currentSequence = [];
        $maxConsecutive = 0;
        
        for ($i = 0; $i < $sortedComparisons->count(); $i++) {
            $comp = $sortedComparisons[$i];
            $currentDate = Carbon::parse($comp['date'])->startOfDay();
            
            if (empty($currentSequence)) {
                $currentSequence[] = $comp;
            } else {
                $lastComp = end($currentSequence);
                $lastDate = Carbon::parse($lastComp['date'])->startOfDay();
                
                // Check if days are consecutive (exactly 1 day difference)
                // Use copy() to avoid modifying the original date
                $nextExpectedDate = $lastDate->copy()->addDay();
                $isConsecutive = $currentDate->equalTo($nextExpectedDate);
                
                \Log::info('DeductionApplicationService: Checking consecutive days', [
                    'last_date' => $lastDate->toDateString(),
                    'current_date' => $currentDate->toDateString(),
                    'next_expected_date' => $nextExpectedDate->toDateString(),
                    'is_consecutive' => $isConsecutive,
                    'diff_in_days' => $currentDate->diffInDays($lastDate),
                ]);
                
                if ($isConsecutive) {
                    // Consecutive day, add to current sequence
                    $currentSequence[] = $comp;
                } else {
                    // Not consecutive, save current sequence if it meets requirement
                    if (count($currentSequence) >= $requiredCount) {
                        $allConsecutiveSequences[] = $currentSequence;
                        \Log::info('DeductionApplicationService: Saved consecutive sequence', [
                            'sequence_length' => count($currentSequence),
                            'sequence_dates' => array_column($currentSequence, 'date'),
                        ]);
                        if (count($currentSequence) > $maxConsecutive) {
                            $maxConsecutive = count($currentSequence);
                        }
                    } else {
                        \Log::info('DeductionApplicationService: Sequence too short, discarding', [
                            'sequence_length' => count($currentSequence),
                            'required_count' => $requiredCount,
                            'sequence_dates' => array_column($currentSequence, 'date'),
                        ]);
                    }
                    $currentSequence = [$comp];
                }
            }
        }
        
        // Add the last sequence if it meets the requirement
        if (count($currentSequence) >= $requiredCount) {
            $allConsecutiveSequences[] = $currentSequence;
            \Log::info('DeductionApplicationService: Saved final consecutive sequence', [
                'sequence_length' => count($currentSequence),
                'sequence_dates' => array_column($currentSequence, 'date'),
            ]);
            if (count($currentSequence) > $maxConsecutive) {
                $maxConsecutive = count($currentSequence);
            }
        } else if (!empty($currentSequence)) {
            \Log::info('DeductionApplicationService: Final sequence too short, discarding', [
                'sequence_length' => count($currentSequence),
                'required_count' => $requiredCount,
                'sequence_dates' => array_column($currentSequence, 'date'),
            ]);
        }

        // Get all triggered days from all sequences (for backward compatibility)
        // But we'll use all_sequences for grouping instead
        $triggeredDays = [];
        foreach ($allConsecutiveSequences as $sequence) {
            foreach ($sequence as $comp) {
                $triggeredDays[] = [
                    'date' => $comp['date'],
                    'day_name' => $comp['day_name'],
                    'details' => $this->getEventDetails($comp),
                ];
            }
        }

        $applies = $maxConsecutive >= $requiredCount;

        \Log::info('DeductionApplicationService: hasConsecutiveOccurrencesWithDetails result', [
            'required_count' => $requiredCount,
            'applies' => $applies,
            'max_consecutive' => $maxConsecutive,
            'sequences_count' => count($allConsecutiveSequences),
            'triggered_days_count' => count($triggeredDays),
            'sequences' => array_map(function($seq) {
                return [
                    'length' => count($seq),
                    'dates' => array_column($seq, 'date'),
                ];
            }, $allConsecutiveSequences),
        ]);

        return [
            'applies' => $applies,
            'reason' => $applies
                ? "تم العثور على {$maxConsecutive} أيام متتالية (المطلوب: {$requiredCount})"
                : "أكبر سلسلة متتالية: {$maxConsecutive} أيام (المطلوب: {$requiredCount})",
            'triggered_days' => $triggeredDays, // Keep for backward compatibility, but use all_sequences for grouping
            'max_consecutive' => $maxConsecutive,
            'total_found' => $sortedComparisons->count(),
            'all_sequences' => $allConsecutiveSequences, // Store all sequences for grouping - THIS IS THE KEY
        ];
    }

    /**
     * Group consecutive days from a sequence into groups of requiredCount (e.g., groups of 3)
     * This function processes a single consecutive sequence and groups it into sets of requiredCount
     */
    private function groupConsecutiveDaysFromSequence(array $sequence, int $requiredCount): array
    {
        \Log::info('DeductionApplicationService: groupConsecutiveDaysFromSequence called', [
            'sequence_length' => count($sequence),
            'required_count' => $requiredCount,
            'sequence_dates' => array_column($sequence, 'date'),
        ]);

        if (empty($sequence) || count($sequence) < $requiredCount) {
            \Log::warning('DeductionApplicationService: Sequence too short for grouping', [
                'sequence_length' => count($sequence),
                'required_count' => $requiredCount,
            ]);
            return [];
        }

        $groups = [];
        
        // Convert sequence items to triggered_days format
        $days = [];
        foreach ($sequence as $comp) {
            // Handle both formats: comparison format and triggered_days format
            $date = $comp['date'] ?? null;
            if (!$date) {
                \Log::warning('DeductionApplicationService: Missing date in sequence item', [
                    'comp_keys' => array_keys($comp),
                ]);
                continue;
            }
            
            // Get day_name if available, otherwise calculate it
            $dayName = $comp['day_name'] ?? null;
            if (!$dayName) {
                $carbonDate = Carbon::parse($date);
                $dayOfWeek = $carbonDate->dayOfWeek; // 0=Sunday, 6=Saturday
                $dayNames = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
                $dayName = $dayNames[$dayOfWeek] ?? 'غير محدد';
            }
            
            // Get details if available, otherwise generate them
            $details = $comp['details'] ?? null;
            if (!$details && isset($comp['comparison_result'])) {
                $details = $this->getEventDetails($comp);
            } elseif (!$details) {
                $details = 'حدث غير محدد';
            }
            
            $days[] = [
                'date' => $date,
                'day_name' => $dayName,
                'details' => $details,
            ];
        }

        \Log::info('DeductionApplicationService: Converted sequence to days format', [
            'days_count' => count($days),
            'days_dates' => array_column($days, 'date'),
        ]);

        // Group consecutive days into groups of requiredCount
        // We'll use non-overlapping groups: take groups of requiredCount, skip incomplete groups
        for ($i = 0; $i <= count($days) - $requiredCount; $i += $requiredCount) {
            $group = array_slice($days, $i, $requiredCount);
            
            \Log::info('DeductionApplicationService: Processing group', [
                'group_index' => count($groups),
                'start_index' => $i,
                'group_count' => count($group),
                'required_count' => $requiredCount,
                'group_dates' => array_column($group, 'date'),
            ]);
            
                if (count($group) === $requiredCount) {
                // Verify these are consecutive days
                $isConsecutive = true;
                $consecutiveDetails = [];
                for ($j = 1; $j < count($group); $j++) {
                    $currentDate = Carbon::parse($group[$j]['date'])->startOfDay();
                    $previousDate = Carbon::parse($group[$j - 1]['date'])->startOfDay();
                    
                    // Use copy() and addDay() to check if current is exactly 1 day after previous
                    $nextExpectedDate = $previousDate->copy()->addDay();
                    $diffInDays = $currentDate->diffInDays($previousDate);
                    $isExactlyOneDayAfter = $currentDate->equalTo($nextExpectedDate);
                    
                    $consecutiveDetails[] = [
                        'previous' => $previousDate->toDateString(),
                        'current' => $currentDate->toDateString(),
                        'next_expected' => $nextExpectedDate->toDateString(),
                        'diff_in_days' => $diffInDays,
                        'is_exactly_one_day_after' => $isExactlyOneDayAfter,
                        'is_consecutive' => $isExactlyOneDayAfter,
                    ];
                    
                    if (!$isExactlyOneDayAfter) {
                        $isConsecutive = false;
                        \Log::warning('DeductionApplicationService: Non-consecutive days found in group', [
                            'group_index' => count($groups),
                            'previous_date' => $previousDate->toDateString(),
                            'current_date' => $currentDate->toDateString(),
                            'next_expected_date' => $nextExpectedDate->toDateString(),
                            'diff_in_days' => $diffInDays,
                            'is_exactly_one_day_after' => $isExactlyOneDayAfter,
                        ]);
                        break;
                    }
                }
                
                \Log::info('DeductionApplicationService: Group consecutive check', [
                    'group_index' => count($groups),
                    'is_consecutive' => $isConsecutive,
                    'consecutive_details' => $consecutiveDetails,
                ]);
                
                if ($isConsecutive) {
                    $groups[] = $group;
                    \Log::info('DeductionApplicationService: Added group', [
                        'group_index' => count($groups) - 1,
                        'group_dates' => array_column($group, 'date'),
                    ]);
                } else {
                    \Log::warning('DeductionApplicationService: Group not consecutive, skipping', [
                        'group_dates' => array_column($group, 'date'),
                    ]);
                }
            } else {
                \Log::info('DeductionApplicationService: Group incomplete, skipping', [
                    'group_count' => count($group),
                    'required_count' => $requiredCount,
                ]);
            }
        }

        \Log::info('DeductionApplicationService: groupConsecutiveDaysFromSequence result', [
            'sequence_length' => count($sequence),
            'required_count' => $requiredCount,
            'groups_count' => count($groups),
            'groups' => array_map(function($group) {
                return [
                    'days_count' => count($group),
                    'days' => array_column($group, 'date'),
                ];
            }, $groups),
        ]);

        return $groups;
    }

    /**
     * Group consecutive days into groups of requiredCount (e.g., groups of 3)
     * @deprecated Use groupConsecutiveDaysFromSequence instead for better accuracy
     */
    private function groupConsecutiveDays(array $triggeredDays, int $requiredCount): array
    {
        \Log::info('DeductionApplicationService: groupConsecutiveDays called', [
            'triggered_days_count' => count($triggeredDays),
            'required_count' => $requiredCount,
        ]);
        
        if (empty($triggeredDays)) {
            \Log::warning('DeductionApplicationService: groupConsecutiveDays - empty triggered days');
            return [];
        }

        // Sort by date
        usort($triggeredDays, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        $groups = [];
        $currentGroup = [];
        
        foreach ($triggeredDays as $day) {
            $currentDate = Carbon::parse($day['date']);
            
            if (empty($currentGroup)) {
                $currentGroup[] = $day;
            } else {
                $lastDate = Carbon::parse(end($currentGroup)['date']);
                $daysDiff = $currentDate->diffInDays($lastDate);
                
                \Log::info('DeductionApplicationService: Checking day', [
                    'current_date' => $day['date'],
                    'last_date' => end($currentGroup)['date'],
                    'days_diff' => $daysDiff,
                    'is_consecutive' => $daysDiff === 1,
                    'current_group_size' => count($currentGroup),
                ]);
                
                // Check if this day is consecutive to the last day in the group
                if ($daysDiff === 1) {
                    $currentGroup[] = $day;
                    
                    // If group reaches requiredCount, save it and start a new group
                    if (count($currentGroup) === $requiredCount) {
                        $groups[] = $currentGroup;
                        \Log::info('DeductionApplicationService: Saved group', [
                            'group_number' => count($groups),
                            'group_days' => array_column($currentGroup, 'date'),
                        ]);
                        $currentGroup = [];
                    }
                } else {
                    // Not consecutive, start a new group
                    // Note: We don't save incomplete groups (< requiredCount)
                    if (count($currentGroup) >= $requiredCount) {
                        // If current group has enough days, save complete groups from it
                        for ($i = 0; $i <= count($currentGroup) - $requiredCount; $i += $requiredCount) {
                            $group = array_slice($currentGroup, $i, $requiredCount);
                            if (count($group) === $requiredCount) {
                                $groups[] = $group;
                                \Log::info('DeductionApplicationService: Saved group from incomplete', [
                                    'group_number' => count($groups),
                                    'group_days' => array_column($group, 'date'),
                                ]);
                            }
                        }
                    }
                    $currentGroup = [$day];
                }
            }
        }
        
        // Handle remaining days in currentGroup
        if (count($currentGroup) >= $requiredCount) {
            for ($i = 0; $i <= count($currentGroup) - $requiredCount; $i += $requiredCount) {
                $group = array_slice($currentGroup, $i, $requiredCount);
                if (count($group) === $requiredCount) {
                    $groups[] = $group;
                    \Log::info('DeductionApplicationService: Saved final group', [
                        'group_number' => count($groups),
                        'group_days' => array_column($group, 'date'),
                    ]);
                }
            }
        }

        \Log::info('DeductionApplicationService: groupConsecutiveDays result', [
            'total_groups' => count($groups),
            'groups' => array_map(function($group) {
                return [
                    'days_count' => count($group),
                    'days' => array_column($group, 'date'),
                ];
            }, $groups),
        ]);

        return $groups;
    }

    /**
     * Check for non-consecutive occurrences
     */
    private function hasNonConsecutiveOccurrences(Collection $comparisons, int $requiredCount): bool
    {
        $result = $this->hasNonConsecutiveOccurrencesWithDetails($comparisons, $requiredCount);
        return $result['applies'];
    }

    /**
     * Check for non-consecutive occurrences with details
     * Groups non-consecutive days into groups of 3 for deduction calculation
     * Each group contains exactly 3 non-consecutive days
     */
    private function hasNonConsecutiveOccurrencesWithDetails(Collection $comparisons, int $requiredCount): array
    {
        $sortedComparisons = $comparisons->sortBy('date')->values();
        $foundCount = $sortedComparisons->count();

        // Map all days to triggered days format
        $allDays = $sortedComparisons->map(function ($comp) {
            return [
                'date' => $comp['date'],
                'day_name' => $comp['day_name'],
                'details' => $this->getEventDetails($comp),
            ];
        })->values()->toArray();

        // Check if we have enough occurrences
        if ($foundCount < $requiredCount) {
            return [
                'applies' => false,
                'reason' => "تم العثور على {$foundCount} حدث غير متتالي (المطلوب: {$requiredCount})",
                'triggered_days' => $allDays,
                'groups' => [],
                'valid_groups' => [],
            ];
        }

        // Group non-consecutive days into groups of 3
        // Logic: 
        // 1. Start a new group
        // 2. Add non-consecutive days to the group
        // 3. When we reach 3 non-consecutive days, save the group and start a new one
        // 4. If a day is consecutive to the last day in the group, start a new group
        $groups = [];
        $currentGroup = [];
        
        foreach ($sortedComparisons as $index => $comp) {
            $currentDate = Carbon::parse($comp['date']);
            
            // Check if this day is consecutive to the last day in current group
            $isConsecutive = false;
            if (!empty($currentGroup)) {
                $lastDayInGroup = end($currentGroup);
                $lastDateInGroup = Carbon::parse($lastDayInGroup['date']);
                $isConsecutive = $currentDate->diffInDays($lastDateInGroup) === 1;
            }
            
            // If consecutive, we need to start a new group (non-consecutive rule)
            if ($isConsecutive && !empty($currentGroup)) {
                // If current group has exactly 3 days, save it
                if (count($currentGroup) === 3) {
                    $groups[] = $currentGroup;
                }
                // Start a new group (the consecutive day will be added below)
                $currentGroup = [];
            }
            
            // Add day to current group
            $currentGroup[] = [
                'date' => $comp['date'],
                'day_name' => $comp['day_name'],
                'details' => $this->getEventDetails($comp),
            ];
            
            // If group reaches exactly 3 days, save it and start a new group
            if (count($currentGroup) === 3) {
                $groups[] = $currentGroup;
                $currentGroup = [];
            }
        }
        
        // Note: Remaining days in currentGroup (< 3) are not included in valid groups
        // Only complete groups of exactly 3 non-consecutive days are considered

        // Filter groups that have exactly 3 days (valid groups)
        $validGroups = array_values(array_filter($groups, fn($group) => count($group) === 3));
        $totalValidGroups = count($validGroups);
        
        // Flatten all days from valid groups for triggered_days
        $triggeredDays = [];
        foreach ($validGroups as $group) {
            $triggeredDays = array_merge($triggeredDays, $group);
        }

        return [
            'applies' => $totalValidGroups > 0,
            'reason' => $totalValidGroups > 0 
                ? "تم العثور على {$foundCount} يوم تأخير غير متتالي، تم تجميعها في {$totalValidGroups} مجموعة (كل مجموعة 3 أيام)"
                : "تم العثور على {$foundCount} حدث غير متتالي، لكن لا توجد مجموعات كاملة من 3 أيام",
            'triggered_days' => $triggeredDays,
            'groups' => $groups, // All complete groups
            'valid_groups' => array_values($validGroups), // Only groups with exactly 3 days
            'total_groups' => $totalValidGroups,
            'total_days' => $foundCount,
        ];
    }

    /**
     * Filter comparisons by time period
     */
    private function filterByTimePeriod(Collection $comparisons, ?string $timePeriod, Carbon $startDate, Carbon $endDate): Collection
    {
        if (!$timePeriod) {
            return $comparisons;
        }

        switch ($timePeriod) {
            case 'daily':
                // Get only today's comparisons
                $today = Carbon::today();
                return $comparisons->filter(fn($c) => Carbon::parse($c['date'])->isToday());

            case 'weekly':
                // Get comparisons from the same week
                $weekStart = $startDate->copy()->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                return $comparisons->filter(function ($c) use ($weekStart, $weekEnd) {
                    $date = Carbon::parse($c['date']);
                    return $date->gte($weekStart) && $date->lte($weekEnd);
                });

            case 'monthly':
                // Already filtered by startDate and endDate (monthly)
                return $comparisons;

            case 'yearly':
                // Get comparisons from the same year
                $year = $startDate->year;
                return $comparisons->filter(fn($c) => Carbon::parse($c['date'])->year === $year);

            default:
                return $comparisons;
        }
    }

    /**
     * Calculate deduction amount based on rule type
     */
    private function calculateDeductionAmount(DeductionRule $rule, $personId, string $personType, array $comparisonData, int $triggeredDaysCount = 1, ?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $deductionType = $rule->effective_deduction_type;
        $baseAmount = $rule->effective_deduction_amount;

        if (!$deductionType || !$baseAmount) {
            return 0;
        }

        $amount = 0;

        switch ($deductionType) {
            case 'fixed':
                $amount = $baseAmount;
                break;

            case 'percentage':
                // Calculate percentage from daily salary, not monthly salary
                $salary = $this->getPersonSalary($personId, $personType);
                $workingDaysPerMonth = $this->getWorkingDaysPerMonth($personId, $personType, $startDate, $endDate);
                $dailySalary = $workingDaysPerMonth > 0 ? $salary / $workingDaysPerMonth : 0;
                // Apply percentage to daily salary for each triggered day
                $amountPerDay = ($dailySalary * $baseAmount) / 100;
                $amount = $amountPerDay * $triggeredDaysCount;
                break;

            case 'daily_salary':
                // Calculate daily salary and multiply by deduction_days and triggeredDaysCount
                $salary = $this->getPersonSalary($personId, $personType);
                $workingDaysPerMonth = $this->getWorkingDaysPerMonth($personId, $personType, $startDate, $endDate);
                $dailySalary = $workingDaysPerMonth > 0 ? $salary / $workingDaysPerMonth : 0;
                $deductionDays = $rule->deduction_days ?? 1;
                // Multiply by triggeredDaysCount to get total deduction for all triggered days
                $amount = $dailySalary * $deductionDays * $triggeredDaysCount;
                break;

            case 'hourly_salary':
                // Calculate hourly rate and multiply by deduction_hours
                $salary = $this->getPersonSalary($personId, $personType);
                $workingHoursPerMonth = $this->getWorkingHoursPerMonth($personId, $personType);
                $hourlyRate = $workingHoursPerMonth > 0 ? $salary / $workingHoursPerMonth : 0;
                $deductionHours = $rule->deduction_hours ?? 0;
                $amount = $hourlyRate * $deductionHours;
                break;

            default:
                return 0;
        }

        // Apply min/max limits
        if ($rule->min_deduction && $amount < $rule->min_deduction) {
            $amount = $rule->min_deduction;
        }
        if ($rule->max_deduction && $amount > $rule->max_deduction) {
            $amount = $rule->max_deduction;
        }

        return round($amount, 2);
    }

    /**
     * Get person's salary
     */
    private function getPersonSalary($personId, string $personType): float
    {
        if ($personType === 'App\Models\Employee') {
            $contract = Contract::where('employee_id', $personId)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($contract) {
                return $contract->total_salary ?? 0;
            }
        } elseif ($personType === 'App\Models\Teacher') {
            $contract = TeacherContract::where('teacher_id', $personId)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($contract) {
                if ($contract->salary_type === 'monthly') {
                    return $contract->salary_amount ?? 0;
                } elseif ($contract->salary_type === 'hourly') {
                    // For hourly, calculate monthly equivalent
                    $weeklyHours = $contract->working_hours_per_week ?? 0;
                    $monthlyHours = $weeklyHours * 4.33; // Average weeks per month
                    return ($contract->hourly_rate ?? 0) * $monthlyHours;
                }
            }
        }

        return 0;
    }

    /**
     * Get working days per month for a person based on actual timetable entries
     * Calculates actual working days in the specified month considering:
     * - Timetable entries (day_of_week)
     * - Individual constraints
     * - Actual calendar dates
     */
    private function getWorkingDaysPerMonth($personId, string $personType, ?Carbon $startDate = null, ?Carbon $endDate = null): int
    {
        // Get timetable entries to determine working days of week
        $timetableEntries = \App\Models\TimetableEntry::where('schedulable_id', $personId)
            ->where('schedulable_type', $personType)
            ->where('is_break', false)
            ->get();

        // Get unique working days of week from timetable entries
        $workingDaysOfWeek = $timetableEntries->pluck('day_of_week')->unique()->values()->toArray();

        // If no timetable entries, try to get from constraints
        if (empty($workingDaysOfWeek)) {
            $person = null;
            if ($personType === 'App\Models\Employee') {
                $person = \App\Models\Employee::with('constraints')->find($personId);
            } elseif ($personType === 'App\Models\Teacher') {
                $person = \App\Models\Teacher::with('constraints')->find($personId);
            }

            if ($person) {
                $requiredDaysConstraint = $person->constraints->firstWhere('constraint_type', 'required_days');
                if ($requiredDaysConstraint) {
                    $constraintValue = $requiredDaysConstraint->value;
                    
                    // Handle different formats: array, JSON string, or direct value
                    if (is_string($constraintValue)) {
                        $parsed = json_decode($constraintValue, true);
                        if (is_array($parsed)) {
                            $constraintValue = $parsed;
                        }
                    }
                    
                    if (is_array($constraintValue) && !empty($constraintValue)) {
                        // Convert from constraints format (0-6) to TimetableEntry format (1-7)
                        // Constraints: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
                        // TimetableEntry: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
                        $workingDaysOfWeek = array_map(function($day) {
                            // Convert 0-6 to 1-7
                            // 0 (Sunday) -> 2
                            // 1 (Monday) -> 3
                            // 2 (Tuesday) -> 4
                            // 3 (Wednesday) -> 5
                            // 4 (Thursday) -> 6
                            // 5 (Friday) -> 7
                            // 6 (Saturday) -> 1
                            return ($day === 0) ? 2 : (($day === 6) ? 1 : ($day + 2));
                        }, $constraintValue);
                        
                        $workingDaysOfWeek = array_unique($workingDaysOfWeek);
                    }
                }
            }
        }

        // If still no working days found, return 0 (should not calculate daily salary)
        if (empty($workingDaysOfWeek)) {
            return 0;
        }

        // For daily salary calculation, always use monthly average based on working days per week
        // This ensures consistent daily salary calculation regardless of the period
        // Formula: working days per week * 4.33 (average weeks per month)
        // We don't use actual working days in the period because daily salary should be based on
        // the standard monthly working days, not the specific period
        $workingDaysPerWeek = count($workingDaysOfWeek);
        // Use round() instead of (int) to properly round 25.98 to 26 instead of truncating to 25
        $workingDaysPerMonth = (int)round($workingDaysPerWeek * 4.33);
        
        \Log::info('DeductionApplicationService: getWorkingDaysPerMonth calculation', [
            'person_id' => $personId,
            'person_type' => $personType,
            'working_days_of_week' => $workingDaysOfWeek,
            'working_days_per_week' => $workingDaysPerWeek,
            'working_days_per_month' => $workingDaysPerMonth,
            'start_date' => $startDate ? $startDate->format('Y-m-d') : null,
            'end_date' => $endDate ? $endDate->format('Y-m-d') : null,
        ]);
        
        return $workingDaysPerMonth;
    }

    /**
     * Get working hours per month for a person
     */
    private function getWorkingHoursPerMonth($personId, string $personType): float
    {
        // Get timetable entries
        $timetableEntries = \App\Models\TimetableEntry::where('schedulable_id', $personId)
            ->where('schedulable_type', $personType)
            ->where('is_break', false)
            ->get();

        // Calculate total hours per week
        $totalMinutesPerWeek = $timetableEntries->sum('work_minutes');
        $hoursPerWeek = $totalMinutesPerWeek / 60;

        // Average hours per month
        return $hoursPerWeek * 4.33;
    }

    /**
     * Get summary of applied conditions
     */
    private function getAppliedConditionsSummary(DeductionRule $rule, array $comparisonData): array
    {
        $conditions = $rule->conditions;
        $summary = [];

        if (isset($conditions['event_type'])) {
            $eventLabels = [
                'late' => 'تأخير',
                'absent' => 'غياب',
                'absent_without_permission' => 'غياب بدون إذن',
                'early_leave' => 'انصراف مبكر',
            ];
            $summary['event_type'] = $eventLabels[$conditions['event_type']] ?? $conditions['event_type'];
        }

        if (isset($conditions['occurrence_type']) && isset($conditions['occurrence_count'])) {
            $occurrenceLabels = [
                'consecutive' => 'متتالية',
                'non_consecutive' => 'غير متتالية',
                'total' => 'إجمالي',
            ];
            $summary['occurrence'] = "{$conditions['occurrence_count']} {$occurrenceLabels[$conditions['occurrence_type']]}";
        }

        if (isset($conditions['time_period'])) {
            $periodLabels = [
                'daily' => 'يومي',
                'weekly' => 'أسبوعي',
                'monthly' => 'شهري',
                'yearly' => 'سنوي',
            ];
            $summary['time_period'] = $periodLabels[$conditions['time_period']] ?? $conditions['time_period'];
        }

        return $summary;
    }
}
