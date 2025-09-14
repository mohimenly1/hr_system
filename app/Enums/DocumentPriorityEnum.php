<?php

namespace App\Enums;

enum DocumentPriorityEnum: string
{
    case NORMAL = 'normal'; // عادي
    case URGENT = 'urgent'; // عاجل
    case IMMEDIATE = 'immediate'; // فوري
}