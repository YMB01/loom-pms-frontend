<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case PropertyManager = 'property_manager';
    case Tenant = 'tenant';
}
