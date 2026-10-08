<?php

namespace JothamLec\MarketingToolkit\Support;

/**
 * Tells what kind of thing a schema.org type is, so the publisher node only prints
 * properties its types accept. A Person has a job title but no logo or price
 * range, an Organization has a logo, address and area served, and a LocalBusiness
 * (a Store, a Restaurant…) also has opening hours, coordinates and a price
 * range. A type not listed here is taken as an Organization subtype, which
 * most remaining schema.org types are (EducationalOrganization, NGO…).
 */
final class SchemaTypes
{
    /** These are LocalBusiness and its common subtypes (schema.org/LocalBusiness); stores go by their name. */
    private const array LOCAL_BUSINESSES = [
        'LocalBusiness', 'AnimalShelter', 'ArchiveOrganization', 'AutomotiveBusiness', 'ChildCare',
        'Dentist', 'DryCleaningOrLaundry', 'EmergencyService', 'EmploymentAgency', 'EntertainmentBusiness',
        'FinancialService', 'FoodEstablishment', 'Bakery', 'BarOrPub', 'CafeOrCoffeeShop', 'FastFoodRestaurant',
        'Restaurant', 'GovernmentOffice', 'HealthAndBeautyBusiness', 'BeautySalon', 'DaySpa', 'HairSalon',
        'HomeAndConstructionBusiness', 'Electrician', 'Plumber', 'InternetCafe', 'LegalService', 'Attorney',
        'Notary', 'Library', 'LodgingBusiness', 'Hotel', 'BedAndBreakfast', 'MedicalBusiness', 'Physician',
        'ProfessionalService', 'RadioStation', 'RealEstateAgent', 'RecyclingCenter', 'SelfStorage',
        'ShoppingCenter', 'SportsActivityLocation', 'HealthClub', 'TelevisionStation',
        'TouristInformationCenter', 'TravelAgency', 'AccountingService', 'AutoRepair', 'Florist',
    ];

    /**
     * @param  list<string>  $types
     */
    public static function isPerson(array $types): bool
    {
        return in_array('Person', $types, true);
    }

    /**
     * @param  list<string>  $types
     */
    public static function isOrganization(array $types): bool
    {
        return collect($types)->contains(fn (string $type) => $type !== 'Person');
    }

    /**
     * Determines whether the types include a LocalBusiness or a store of any kind (BookStore, GardenStore…).
     *
     * @param  list<string>  $types
     */
    public static function isLocalBusiness(array $types): bool
    {
        return collect($types)->contains(fn (string $type) => in_array($type, self::LOCAL_BUSINESSES, true) || str_ends_with($type, 'Store'));
    }
}
