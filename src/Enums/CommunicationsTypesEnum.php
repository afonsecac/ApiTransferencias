<?php

namespace App\Enums;

enum CommunicationsTypesEnum: string
{
    case TALK_TIME = 'TALKTIME';
    case DATA = 'DATA';
    case SMS = 'SMS';
    case CREDITS = 'CREDITS';
}
