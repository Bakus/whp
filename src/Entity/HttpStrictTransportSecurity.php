<?php

namespace App\Entity;

enum HttpStrictTransportSecurity: string
{
    case NO = 'NO';
    case SIMPLE = 'max-age=31536000';
    case WITH_SUBDOMAINS = 'max-age=31536000; includeSubDomains';
    case FULL = 'max-age=31536000; includeSubDomains; preload';
}
