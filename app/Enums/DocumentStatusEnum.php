<?php

namespace App\Enums;

enum DocumentStatusEnum: string
{
    case DRAFT = 'draft'; // مسودة
    case IN_REVIEW = 'in_review'; // قيد المراجعة
    case APPROVED = 'approved'; // معتمد
    case SENT = 'sent'; // مرسل
    case EXECUTED = 'executed'; // منفذ
    case ARCHIVED = 'archived'; // مؤرشف
    case REJECTED = 'rejected'; // مرفوض
}