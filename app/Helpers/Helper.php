<?php
/**
 * Created by PhpStorm.
 * User: kavramenko
 * Date: 6/22/2018
 * Time: 11:50 AM
 */

namespace App\Helpers;

use App\User;
use \RecursiveIteratorIterator;
use \RecursiveArrayIterator;

class Helper
{


    public static function getUserData(array $requestArray): array
    {
        $resultArray = array();
        foreach (new RecursiveIteratorIterator(
                     new RecursiveArrayIterator($requestArray),
                     RecursiveIteratorIterator::SELF_FIRST
                 ) as $key => $array) {
            if ($key == 'from' && isset($array['is_bot'])) {
                if ($array['is_bot'] == true)
                    continue;
                if (!empty($resultArray))
                    continue;
                $resultArray['chat_id'] = $array['id'];
                $resultArray['first_name'] = $array['first_name'];
                $resultArray['last_name'] = $array['last_name'];
                $resultArray['username'] = (isset($array['username']) ? $array['username'] : null);
            }
        }

        if (empty($resultArray))
            throw new \Exception('Cannot find user with request data given');
        return $resultArray;
    }

    static public function getInputData(array $requestArray): array
    {
        $resultArray = array();
        $keys = array_keys($requestArray);
        $key = (isset($keys[1]) ? $keys[1] : null);
        if ($key == null)
            throw new \Exception('Cannot find input with request data given');
        else {
            $resultArray['type'] = $key;
            $resultArray['data'] = $requestArray[$key];
        }
        return $resultArray;
    }

    static public function createUserDefault(array $input)
    {

    }

    static public function getCityAndCountryGoogle(string $coordinates)
    {
        $endpoint = "https://maps.googleapis.com/maps/api/geocode/json?latlng={COORDINATES}&key={API_KEY}";
        $endpoint = str_replace("{API_KEY}", env('GOOGLE_API_TOKEN'), $endpoint);
        $endpoint = str_replace("{COORDINATES}", $coordinates, $endpoint);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $endpoint,
            CURLINFO_HEADER_OUT => 1,
            CURLOPT_HTTPHEADER => [
                'Accept:application/json',
            ]
        ]);

        $response = curl_exec($curl);

        $response = json_decode($response, true);

        $found = false;
        $returnVal = '{CITY},{COUNTRY_CODE}';
        foreach ($response['result'] as $result) {
            foreach ($result['address_components'] as $address) {
                if ($address['types'] == ['locality', 'political'])
                    $returnVal = str_replace("{CITY}", $address['long_name'], $returnVal);
                if ($address['types'] == ['country', 'political'])
                    $returnVal = str_replace("{COUNTRY_CODE}", $address['short_name'], $returnVal);
                if ((!strpos($returnVal, '{CITY}')) && (!strpos($returnVal, '{COUNTRY_CODE}'))) {
                    $found = true;
                    break;
                }
            }
            if ($found)
                break;
        }
        if ($found)
            return $returnVal;
        else
            throw new \Exception('Can\'t find city and country');
    }
}