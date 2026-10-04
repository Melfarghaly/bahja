<?php

namespace App\Enums;

/**
 * Commercial capabilities a nursery can be entitled to. Granted by the plan,
 * by add-ons, or by a per-tenant override (see EntitlementService).
 */
enum Feature: string
{
    case Attendance = 'attendance';
    case LostChild = 'lost_child';
    case Wall = 'wall';
    case ThankYouStars = 'thank_you_stars';
    case FinanceLedger = 'finance_ledger';
    case PickupPasses = 'pickup_passes';
    case Messaging = 'messaging';
    case HealthRecords = 'health_records';
    case AutoCollection = 'auto_collection';
    case AdmissionsCrm = 'admissions_crm';
    case PulseReport = 'pulse_report';
    case ReportsExport = 'reports_export';
    case RewardsCatalog = 'rewards_catalog';
    case WhiteLabel = 'white_label';
    case PublicApi = 'public_api';
    case MultiBranch = 'multi_branch';
    case Buses = 'buses';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'الحضور والانصراف بالـ QR',
            self::LostChild => 'نظام الطفل التائه',
            self::Wall => 'الحائط الزمني',
            self::ThankYouStars => 'نجوم الشكر',
            self::FinanceLedger => 'السجل المالي والإيصالات',
            self::PickupPasses => 'QR ديناميكي وتصاريح الاستلام',
            self::Messaging => 'رسائل المعلمة وولي الأمر',
            self::HealthRecords => 'سجلات السلوك والصحة',
            self::AutoCollection => 'بهجة باي: التحصيل الآلي والتذكير',
            self::AdmissionsCrm => 'قمع القبول',
            self::PulseReport => 'تقرير النبض وإنذار الانسحاب',
            self::ReportsExport => 'التقارير والتصدير',
            self::RewardsCatalog => 'كتالوج مكافآت المعلمات',
            self::WhiteLabel => 'الهوية الخاصة (White-Label)',
            self::PublicApi => 'الـ API العام',
            self::MultiBranch => 'لوحة الفروع الموحّدة',
            self::Buses => 'الحافلات',
        };
    }
}
