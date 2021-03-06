<?php
/**
 * Created by PhpStorm.
 * User: kavramenko
 * Date: 6/27/2018
 * Time: 1:51 PM
 */

namespace App\Helpers;


class Localize
{

    private $locale_strings;
    public $current;

    public function __construct(string $locale) {
        try {
            $this->locale_strings = json_decode(file_get_contents(base_path('app/Locales/' . $locale . '.json')), true);
        } catch (\Exception $e) {
            $this->locale_strings = json_decode(file_get_contents(base_path('app/Locales/en.json')), true);
        }
        $this->current = $this->locale_strings['shortLang'];
    }

    public function getString(string $str)
    {
        if (empty($str))
            return '';
        if (!isset($this->locale_strings[$str]))
            return $str;
        return $this->locale_strings[$str];
    }
    
    public static function getShortLocales(){
  	  $localeArr = array_diff(scandir(base_path('app/Locales')), array('..', '.'));
  	  return array_filter(array_map( function ($a) { 
  					if(strpos($a, '.json') !== false)
  						return str_replace('.json', '', $a);
  					else
  						return '';
  				} , $localeArr));
    }

    public function getAllLocales()
    {
        $localeArr = array_diff(scandir(base_path('app/Locales')), array('..', '.'));
        $result = array();
        foreach ($localeArr as $locale) {
            try {
                $data = json_decode(file_get_contents(base_path('app/Locales/' . $locale)), true);
                if ($data != null) {
                    $tmp = array();
                    $tmp['short'] = $data['shortLang'];
                    $tmp['full'] = $data['lang'];
                    $result[] = $tmp;
                }
            } catch (\Exception $e) {
                continue;
            }

        }
        return $result;
    }

    public function setLocale(string $locale){
        try {
            $this->locale_strings = json_decode(file_get_contents(base_path('app/Locales/' . $locale . '.json')), true);
        } catch (\Exception $e) {
            $this->locale_strings = json_decode(file_get_contents(base_path('app/Locales/en.json')), true);
        }
        $this->current = $this->locale_strings['shortLang'];
    }
}