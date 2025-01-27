<?php

namespace App\Enums;

enum NavigationTypeEnum: string
{
    case ASIDE = 'aside';
    case BASIC = 'basic';
    case COLLAPSABLE = 'collapsable';
    case DIVIDER = 'divider';
    case GROUP = 'group';
    case SPACER = 'spacer';
}
