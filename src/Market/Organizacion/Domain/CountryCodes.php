<?php

declare(strict_types=1);

namespace App\Market\Organizacion\Domain;

/**
 * Códigos de país. El `Client` guarda el país en ISO-3166 alfa-3 y la unidad organizativa lo guarda en alfa-2
 * (COMR-272); el dashboard muestra los dos. Aquí solo vive la tabla de conversión, sin depender de ninguna
 * librería externa. Son los 249 países ISO-3166 más Kosovo (XK, XKX), y debe coincidir con la lista del
 * select de país del dashboard (`countries.util.ts`).
 */
final class CountryCodes
{
    /** alfa-2 => alfa-3 */
    private const ISO2_TO_ISO3 = [
        'AD' => 'AND', 'AE' => 'ARE', 'AF' => 'AFG', 'AG' => 'ATG', 'AI' => 'AIA', 'AL' => 'ALB', 'AM' => 'ARM',
        'AO' => 'AGO', 'AQ' => 'ATA', 'AR' => 'ARG', 'AS' => 'ASM', 'AT' => 'AUT', 'AU' => 'AUS', 'AW' => 'ABW',
        'AX' => 'ALA', 'AZ' => 'AZE', 'BA' => 'BIH', 'BB' => 'BRB', 'BD' => 'BGD', 'BE' => 'BEL', 'BF' => 'BFA',
        'BG' => 'BGR', 'BH' => 'BHR', 'BI' => 'BDI', 'BJ' => 'BEN', 'BL' => 'BLM', 'BM' => 'BMU', 'BN' => 'BRN',
        'BO' => 'BOL', 'BQ' => 'BES', 'BR' => 'BRA', 'BS' => 'BHS', 'BT' => 'BTN', 'BV' => 'BVT', 'BW' => 'BWA',
        'BY' => 'BLR', 'BZ' => 'BLZ', 'CA' => 'CAN', 'CC' => 'CCK', 'CD' => 'COD', 'CF' => 'CAF', 'CG' => 'COG',
        'CH' => 'CHE', 'CI' => 'CIV', 'CK' => 'COK', 'CL' => 'CHL', 'CM' => 'CMR', 'CN' => 'CHN', 'CO' => 'COL',
        'CR' => 'CRI', 'CU' => 'CUB', 'CV' => 'CPV', 'CW' => 'CUW', 'CX' => 'CXR', 'CY' => 'CYP', 'CZ' => 'CZE',
        'DE' => 'DEU', 'DJ' => 'DJI', 'DK' => 'DNK', 'DM' => 'DMA', 'DO' => 'DOM', 'DZ' => 'DZA', 'EC' => 'ECU',
        'EE' => 'EST', 'EG' => 'EGY', 'EH' => 'ESH', 'ER' => 'ERI', 'ES' => 'ESP', 'ET' => 'ETH', 'FI' => 'FIN',
        'FJ' => 'FJI', 'FK' => 'FLK', 'FM' => 'FSM', 'FO' => 'FRO', 'FR' => 'FRA', 'GA' => 'GAB', 'GB' => 'GBR',
        'GD' => 'GRD', 'GE' => 'GEO', 'GF' => 'GUF', 'GG' => 'GGY', 'GH' => 'GHA', 'GI' => 'GIB', 'GL' => 'GRL',
        'GM' => 'GMB', 'GN' => 'GIN', 'GP' => 'GLP', 'GQ' => 'GNQ', 'GR' => 'GRC', 'GS' => 'SGS', 'GT' => 'GTM',
        'GU' => 'GUM', 'GW' => 'GNB', 'GY' => 'GUY', 'HK' => 'HKG', 'HM' => 'HMD', 'HN' => 'HND', 'HR' => 'HRV',
        'HT' => 'HTI', 'HU' => 'HUN', 'ID' => 'IDN', 'IE' => 'IRL', 'IL' => 'ISR', 'IM' => 'IMN', 'IN' => 'IND',
        'IO' => 'IOT', 'IQ' => 'IRQ', 'IR' => 'IRN', 'IS' => 'ISL', 'IT' => 'ITA', 'JE' => 'JEY', 'JM' => 'JAM',
        'JO' => 'JOR', 'JP' => 'JPN', 'KE' => 'KEN', 'KG' => 'KGZ', 'KH' => 'KHM', 'KI' => 'KIR', 'KM' => 'COM',
        'KN' => 'KNA', 'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KY' => 'CYM', 'KZ' => 'KAZ', 'LA' => 'LAO',
        'LB' => 'LBN', 'LC' => 'LCA', 'LI' => 'LIE', 'LK' => 'LKA', 'LR' => 'LBR', 'LS' => 'LSO', 'LT' => 'LTU',
        'LU' => 'LUX', 'LV' => 'LVA', 'LY' => 'LBY', 'MA' => 'MAR', 'MC' => 'MCO', 'MD' => 'MDA', 'ME' => 'MNE',
        'MF' => 'MAF', 'MG' => 'MDG', 'MH' => 'MHL', 'MK' => 'MKD', 'ML' => 'MLI', 'MM' => 'MMR', 'MN' => 'MNG',
        'MO' => 'MAC', 'MP' => 'MNP', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MS' => 'MSR', 'MT' => 'MLT', 'MU' => 'MUS',
        'MV' => 'MDV', 'MW' => 'MWI', 'MX' => 'MEX', 'MY' => 'MYS', 'MZ' => 'MOZ', 'NA' => 'NAM', 'NC' => 'NCL',
        'NE' => 'NER', 'NF' => 'NFK', 'NG' => 'NGA', 'NI' => 'NIC', 'NL' => 'NLD', 'NO' => 'NOR', 'NP' => 'NPL',
        'NR' => 'NRU', 'NU' => 'NIU', 'NZ' => 'NZL', 'OM' => 'OMN', 'PA' => 'PAN', 'PE' => 'PER', 'PF' => 'PYF',
        'PG' => 'PNG', 'PH' => 'PHL', 'PK' => 'PAK', 'PL' => 'POL', 'PM' => 'SPM', 'PN' => 'PCN', 'PR' => 'PRI',
        'PS' => 'PSE', 'PT' => 'PRT', 'PW' => 'PLW', 'PY' => 'PRY', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU',
        'RS' => 'SRB', 'RU' => 'RUS', 'RW' => 'RWA', 'SA' => 'SAU', 'SB' => 'SLB', 'SC' => 'SYC', 'SD' => 'SDN',
        'SE' => 'SWE', 'SG' => 'SGP', 'SH' => 'SHN', 'SI' => 'SVN', 'SJ' => 'SJM', 'SK' => 'SVK', 'SL' => 'SLE',
        'SM' => 'SMR', 'SN' => 'SEN', 'SO' => 'SOM', 'SR' => 'SUR', 'SS' => 'SSD', 'ST' => 'STP', 'SV' => 'SLV',
        'SX' => 'SXM', 'SY' => 'SYR', 'SZ' => 'SWZ', 'TC' => 'TCA', 'TD' => 'TCD', 'TF' => 'ATF', 'TG' => 'TGO',
        'TH' => 'THA', 'TJ' => 'TJK', 'TK' => 'TKL', 'TL' => 'TLS', 'TM' => 'TKM', 'TN' => 'TUN', 'TO' => 'TON',
        'TR' => 'TUR', 'TT' => 'TTO', 'TV' => 'TUV', 'TW' => 'TWN', 'TZ' => 'TZA', 'UA' => 'UKR', 'UG' => 'UGA',
        'UM' => 'UMI', 'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB', 'VA' => 'VAT', 'VC' => 'VCT', 'VE' => 'VEN',
        'VG' => 'VGB', 'VI' => 'VIR', 'VN' => 'VNM', 'VU' => 'VUT', 'WF' => 'WLF', 'WS' => 'WSM', 'XK' => 'XKX',
        'YE' => 'YEM', 'YT' => 'MYT', 'ZA' => 'ZAF', 'ZM' => 'ZMB', 'ZW' => 'ZWE',
    ];

    /**
     * @return array<string, string> alfa-2 => alfa-3
     */
    public static function all(): array
    {
        return self::ISO2_TO_ISO3;
    }

    /**
     * @throws UnknownBuyerCountry si el código no es un alfa-3 conocido (p. ej. un código inventado)
     */
    public static function iso2FromIso3(string $iso3): string
    {
        $normalized = strtoupper(trim($iso3));
        $iso2 = array_search($normalized, self::ISO2_TO_ISO3, true);

        return $iso2 !== false ? (string) $iso2 : throw UnknownBuyerCountry::forCode($iso3);
    }

    /**
     * @return string|null null si el alfa-2 no está en la tabla (p. ej. un dato antiguo)
     */
    public static function iso3FromIso2(string $iso2): ?string
    {
        return self::ISO2_TO_ISO3[strtoupper(trim($iso2))] ?? null;
    }
}
