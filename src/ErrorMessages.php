<?php

namespace App;

/**
 * Central dictionary for gateway error codes with Serbian and English translations.
 * Maps Allsecure gateway error codes to user-friendly messages.
 */
final class ErrorMessages
{
    /**
     * Error code to translated messages mapping
     */
    private static $messages = array(
        // General Errors & Validation Errors (1000-1010, 9999)
        1000 => array(
            'sr' => 'Zahtev nije mogao biti obrađen. Molimo pokušajte ponovo.',
            'en' => 'Request failed. Please try again.',
        ),
        1001 => array(
            'sr' => 'Neočekivan odgovor servera. Molimo pokušajte ponovo.',
            'en' => 'Invalid response from server. Please try again.',
        ),
        1002 => array(
            'sr' => 'Podaci zahteva nisu ispravni ili su nepotpuni.',
            'en' => 'Request data are malformed or missing.',
        ),
        1003 => array(
            'sr' => 'Transakcija nije mogla biti obrađena. Molimo pokušajte ponovo.',
            'en' => 'Transaction could not be processed. Please try again.',
        ),
        1004 => array(
            'sr' => 'Digitalni potpis zahteva nije validan.',
            'en' => 'Invalid request signature.',
        ),
        1005 => array(
            'sr' => 'Format zahteva nije validan.',
            'en' => 'Invalid XML format.',
        ),
        1006 => array(
            'sr' => 'Uslovi za obradu nisu ispunjeni. Molimo pokušajte sa drugim metodom.',
            'en' => 'Preconditions failed. Please try with another method.',
        ),
        1007 => array(
            'sr' => 'Greška u konfiguraciji. Molimo kontaktirajte podršku.',
            'en' => 'Configuration error. Please contact support.',
        ),
        1008 => array(
            'sr' => 'Sistemska greška. Molimo pokušajte ponovo kasnije.',
            'en' => 'Unexpected system error. Please try again later.',
        ),
        1009 => array(
            'sr' => 'Previše zahteva. Molimo pokušajte ponovo za nekoliko minuta.',
            'en' => 'Rate limit exceeded. Please try again later.',
        ),
        1010 => array(
            'sr' => 'Sistem je u održavanju. Molimo pokušajte ponovo kasnije.',
            'en' => 'System is under maintenance. Please try again later.',
        ),

        // Payment Errors (2001-2022)
        2001 => array(
            'sr' => 'Kartrica je blokirana. Molimo kontaktirajte vašu banku.',
            'en' => 'Account has been closed. Please contact your bank.',
        ),
        2002 => array(
            'sr' => 'Otkazali ste plaćanje.',
            'en' => 'Payment cancelled by user.',
        ),
        2003 => array(
            'sr' => 'Plaćanje je odbijena. Molimo pokušajte sa drugom karticom ili metodom.',
            'en' => 'Transaction declined. Please try another card or payment method.',
        ),
        2004 => array(
            'sr' => 'Dostignut ste limit za ovu transakciju.',
            'en' => 'Quota limit reached.',
        ),
        2005 => array(
            'sr' => 'Vreme je isteklo. Molimo pokušajte ponovo.',
            'en' => 'Payment expired. Please try again.',
        ),
        2006 => array(
            'sr' => 'Nedovoljno sredstava na kartici.',
            'en' => 'Insufficient funds.',
        ),
        2007 => array(
            'sr' => 'Podaci o plaćanju nisu ispravni.',
            'en' => 'Incorrect payment information.',
        ),
        2008 => array(
            'sr' => 'Kartrica nije validna.',
            'en' => 'Invalid card.',
        ),
        2009 => array(
            'sr' => 'Kartrica je istekla.',
            'en' => 'Card is expired.',
        ),
        2010 => array(
            'sr' => 'Kartrica je označena kao sumnjiva. Molimo kontaktirajte banku.',
            'en' => 'Fraudulent card detected. Please contact your bank.',
        ),
        2011 => array(
            'sr' => 'Ovaj tip kartrice nije podržan.',
            'en' => 'Unsupported card type.',
        ),
        2012 => array(
            'sr' => 'Plaćanje je otkazano.',
            'en' => 'Transaction cancelled.',
        ),
        2013 => array(
            'sr' => 'Transakcija je blokirana iz sigurnosnih razloga.',
            'en' => 'Transaction blocked by risk check.',
        ),
        2014 => array(
            'sr' => 'Kartrica je trebalo biti preuzeće od banke.',
            'en' => 'Card pickup required.',
        ),
        2015 => array(
            'sr' => 'Kartrica je prijavljena kao izgubljena.',
            'en' => 'Lost card.',
        ),
        2016 => array(
            'sr' => 'Kartrica je prijavljena kao ukradena.',
            'en' => 'Stolen card.',
        ),
        2017 => array(
            'sr' => 'IBAN broj nije validan.',
            'en' => 'IBAN is invalid.',
        ),
        2018 => array(
            'sr' => 'BIC kod nije validan.',
            'en' => 'BIC code is invalid.',
        ),
        2019 => array(
            'sr' => 'Podaci kupца nisu validni.',
            'en' => 'Customer data are invalid.',
        ),
        2020 => array(
            'sr' => 'CVV kod je obavezan.',
            'en' => 'CVV code is required.',
        ),
        2021 => array(
            'sr' => 'Autentifikacija kartrice je neuspešna. Pokušajte ponovo ili koristite drugu kartiću.',
            'en' => '3D-Secure verification failed. Please try again or use another card.',
        ),
        2022 => array(
            'sr' => 'Autentifikacija kartrice je odbijena.',
            'en' => '3D-Secure verification soft declined.',
        ),

        // Status API Errors (8001)
        8001 => array(
            'sr' => 'Transakcija nije pronađena.',
            'en' => 'Transaction not found.',
        ),

        // Schedule API Errors (7001-7070)
        7001 => array(
            'sr' => 'Zahtev za raspored nije validan.',
            'en' => 'Schedule request is invalid.',
        ),
        7002 => array(
            'sr' => 'Zahtev za raspored nije mogao biti obrađen.',
            'en' => 'Schedule request failed.',
        ),
        7005 => array(
            'sr' => 'Akcija rasporeda nije validna.',
            'en' => 'Schedule action is not valid.',
        ),
        7010 => array(
            'sr' => 'ID registracije je obavezan.',
            'en' => 'Registration ID is required.',
        ),
        7020 => array(
            'sr' => 'ID registracije nije validan.',
            'en' => 'Registration ID is not valid.',
        ),
        7030 => array(
            'sr' => 'Referentna transakcija nije registracija.',
            'en' => 'Reference transaction is not a register.',
        ),
        7035 => array(
            'sr' => 'Početna transakcija nije registracija.',
            'en' => 'Initial transaction is not a register.',
        ),
        7036 => array(
            'sr' => 'Razlika između početne i druge transakcije mora biti veća od 24 sata.',
            'en' => 'Period between transactions must be greater than 24 hours.',
        ),
        7040 => array(
            'sr' => 'ID rasporeda nije validan ili ne pripada ovoj konekciji.',
            'en' => 'Schedule ID is invalid or does not match the connector.',
        ),
        7050 => array(
            'sr' => 'Datum početka rasporeda nije validan ili je star više od 24 sata.',
            'en' => 'Start date is invalid or older than 24 hours.',
        ),
        7060 => array(
            'sr' => 'Datum nastavka rasporeda nije validan ili je star više od 24 sata.',
            'en' => 'Continue date is invalid or older than 24 hours.',
        ),
        7070 => array(
            'sr' => 'Status rasporeda nije validan za ovu operaciju.',
            'en' => 'Schedule status is not valid for this operation.',
        ),

        // Network Errors (3001-3005)
        3001 => array(
            'sr' => 'Veza je istekla. Molimo pokušajte ponovo.',
            'en' => 'Connection timeout. Please try again.',
        ),
        3002 => array(
            'sr' => 'Operacija nije dozvoljena.',
            'en' => 'Operation not allowed.',
        ),
        3003 => array(
            'sr' => 'Servis je privremeno nedostupan. Molimo pokušajte ponovo kasnije.',
            'en' => 'Service temporarily unavailable. Please try again later.',
        ),
        3004 => array(
            'sr' => 'Transakcija sa ovim ID-om je već obrađena.',
            'en' => 'Duplicate transaction ID.',
        ),
        3005 => array(
            'sr' => 'Greška pri komunikaciji sa serverom. Molimo pokušajte ponovo.',
            'en' => 'Communication error. Please try again.',
        ),

        // Post-Processing Errors (4001-4002)
        4001 => array(
            'sr' => 'Storno je opozvano.',
            'en' => 'Chargeback has been reverted.',
        ),
        4002 => array(
            'sr' => 'Prigovor na transakciju je otvoren.',
            'en' => 'Payment dispute has been filed.',
        ),
    );

    /**
     * Translate an error code to user-friendly message in the specified language
     *
     * @param int $code Error code
     * @param string $language Language code ('sr' for Serbian, 'en' for English)
     * @return string Translated error message
     */
    public static function translate(int $code, string $language = 'en'): string
    {
        // Normalize language code
        $language = strtolower(trim($language));
        if ($language !== 'sr' && $language !== 'en') {
            $language = 'en';
        }

        // Return translated message if available
        if (isset(self::$messages[$code]) && isset(self::$messages[$code][$language])) {
            return self::$messages[$code][$language];
        }

        // Fallback for unknown codes
        $unknownMessages = array(
            'sr' => 'Došlo je do greške pri obradi transakcije. Molimo pokušajte ponovo ili kontaktirajte podršku.',
            'en' => 'An error occurred while processing your transaction. Please try again or contact support.',
        );

        return $unknownMessages[$language] ?? $unknownMessages['en'];
    }

    /**
     * Get all error codes that have translations
     *
     * @return array List of error codes
     */
    public static function getAvailableCodes(): array
    {
        return array_keys(self::$messages);
    }

    /**
     * Check if an error code has a translation
     *
     * @param int $code Error code
     * @return bool True if translation exists
     */
    public static function hasTranslation(int $code): bool
    {
        return isset(self::$messages[$code]);
    }

    /**
     * Get all translations for a specific error code
     *
     * @param int $code Error code
     * @return array|null Array with 'sr' and 'en' keys, or null if not found
     */
    public static function getAll(int $code): ?array
    {
        return self::$messages[$code] ?? null;
    }
}
