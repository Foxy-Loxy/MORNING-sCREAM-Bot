<?php


namespace App\ModelClass;

use App\Helpers\Helper;
use App\Helpers\Localize;
use App\NewsCache;
use App\User;
use App\WeatherCache;
use Carbon\Carbon;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Exceptions;


class Weather
{

    static public function scheduleCall(User $user)
    {
        $locale = app(Localize::class);
        ///////////////////////////////////////////////////
        //  $locale->getString('') //
        ///////////////////////////////////////////////////
        $setKeyboard = Keyboard::make([
            'keyboard' => [
                [$locale->getString("weather_menu_UnitToggleKbd")],
                [$locale->getString("weather_menu_SetLocKbd")],
                [$locale->getString("cancel")]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        $tmp = \App\Weather::where('chat_id', $user->chat_id)->get();
        if ($tmp->isEmpty())
            \App\Weather::create([
                'chat_id' => $user->chat_id
            ]);

        $user->update([
            'function' => \App\Weather::NAME,
            'function_state' => 'WAITING_FOR_SETTING'
        ]);
        Telegram::sendMessage([
            'chat_id' => $user->chat_id,
            'text' => $locale->getString("weather_menu_Enter"),
            'reply_markup' => $setKeyboard
        ]);
    }

    static public function scheduleConfirm(User $user, $input, Keyboard $exitKbd)
    {
        $locale = app(Localize::class);
        $setKeyboard = Keyboard::make([
            'keyboard' => [
                [$locale->getString("weather_menu_UnitToggleKbd")],
                [$locale->getString("weather_menu_SetLocKbd")],
                [$locale->getString("cancel")]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        $canKeyboard = Keyboard::make([
            'keyboard' => [
                [Keyboard::button([
                    'text' =>  $locale->getString("weather_GetLoc_KbdMsg"),
                    'request_location' => true
                ])],
                [ $locale->getString("cancel") ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);
        
//                Telegram::sendMessage([
//                        'chat_id' => $user->chat_id,
//                        'text' => print_r($canKeyboard, true),
//                        'reply_markup' => $exitKbd
//                    ]);

        if ($user->function == \App\Weather::NAME && $user->function_state != null) {

            switch ($input) {

                case $locale->getString("cancel"):
                    $user->update([
                        'function' => null,
                        'function_state' => null
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("canceled"),
                        'reply_markup' => $exitKbd
                    ]);
                    return false;
                    break;

                case $locale->getString("weather_menu_UnitToggleKbd"):
                    $weather = \App\Weather::where('chat_id', $user->chat_id)->get()[0];
                    if ($weather->units == 'metric')
                        $weather->update([
                            'units' => 'imperial'
                        ]);
                    else
                        $weather->update([
                            'units' => 'metric'
                        ]);
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("weather_ToggleResult") . $locale->getString($weather->units),
                        'reply_markup' => $setKeyboard
                    ]);
                    break;

                case $locale->getString("weather_menu_SetLocKbd"):
                    $user->update([
                        'function_state' => 'WAITING_FOR_LOCATION'
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("weather_SetLoc_Request"),
                        'reply_markup' => $canKeyboard
                    ]);
                    return true;
                    break;
            }
            switch ($user->function_state) {

                case 'WAITING_FOR_LOCATION':
//                    if (!isset($input['latitude']))
//                        if (!isset($input['text'])) {
//                            Telegram::sendMessage([
//                                'chat_id' => $user->chat_id,
//                                'text' => 'Send me your location to set weather origin. It can be both geo-pin or location`s name ' . print_r($input, true),
//                                'reply_markup' => $canKeyboard
//                            ]);
//                            return false;
//                        } else
//                            $input = $input['text'];
                    $weatherUser = \App\Weather::where('chat_id', $user->chat_id)->get()[0];
//                        Telegram::sendMessage([
//                            'chat_id' => $user->chat_id,
//                            'text' => print_r(Keyboard::make([
//                          		[['text' => 'qwe', 'request_location' => true]]
//                            ]), true
//                            ),
//                            'reply_markup' => $canKeyboard
//                        ]);
                    try {
                        $data = Helper::getCityAndCountryGoogle($input);
                    } catch (\Exception $e) {
                        Telegram::sendMessage([
                            'chat_id' => $user->chat_id,
                            'text' => $locale->getString("weather_SetLoc_Fail"),
                            'reply_markup' => $canKeyboard
                        ]);
                        return true;
                    }
                    $weatherUser->update([
                        'lat' => $data['location_lat'],
                        'lon' => $data['location_lon'],
                        'location' => $data['location_string']
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("weather_SetLoc_Success") . $weatherUser->location,
                        'reply_markup' => $canKeyboard
                    ]);
                    try {
                        $tz = Helper::getTimeZoneByCoordinatesGoogle($data['location_lat'], $data['location_lon']);
                    } catch (\Exception $e) {
                        Telegram::sendMessage([
                            'chat_id' => $user->chat_id,
                            'text' => $locale->getString("weather_SetLoc_TZ_Fail"),
                            'reply_markup' => $canKeyboard
                        ]);
                        return true;
                    }
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("weather_SetLoc_TZ_Success") . $tz,
                        'reply_markup' => $canKeyboard
                    ]);
                    $weatherUser->user->schedule->update([
                        'utc' => $tz,
                        'utc_time' => Carbon::parse($weatherUser->user->schedule->time)->setTimezone($tz)->format('H:i')
                    ]);
                    break;
            }
        }
        return true;
    }

    static public function deliver(User $user)
    {
        $locale = app(Localize::class);
        $location = $user->weather->location;
        $response = '';
        $cache = WeatherCache::where('location', $location)->where('units', $user->weather->units)->get();
        if ($cache->isNotEmpty()) {
            $cache = $cache[0];
            $response = $cache->content;
        } else {
            $i = 0;
            while (!Weather::fetch($location, $user->weather->units, $user->weather->lat, $user->weather->lon)) {
                if ($i == 4) {
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString("weather_delivery_ZeroResults") . ucfirst($location) ,
                        'parse_mode' => 'html'
                    ]);
                    break;
                }
                $i++;
            }

        }

        $all = array();

        if (isset($response)) {
            $cache = WeatherCache::where('location', $location)->where('units', $user->weather->units)->get();
            if ($cache->isNotEmpty()) {
                $cache = $cache[0];
                $all = json_decode($cache->content, true);
            } else
                $all = json_decode($response);

            if (is_array($all)) {
                Telegram::sendMessage([
                    'chat_id' => $user->chat_id,
                    'text' => $locale->getString("weather_delivery_Delivery") . $location,
                    'parse_mode' => 'html'
                ]);
                
				$offset = Carbon::now()->timezone($user->schedule->utc)->format('Z');
      			$cacheBeginDate = Carbon::createFromTimestamp($all[0]['dt'])->startOfDay();
      			$aimTime = Carbon::now()->timezone($user->schedule->utc);
      			$cacheAimDate = $cacheBeginDate;
      			$right = array();
        
      			foreach ($all as $entry) {
      			$entryCarbon = Carbon::createFromTimestamp($entry['dt'])->addSeconds($offset);
      				if($entryCarbon->format('d-m-Y') == $cacheAimDate->format('d-m-Y') && $entryCarbon->format('H') >= $aimTime->format('H'))
      					$right[]= $entry;
      					
      			}

                $time = (isset($right[0]) ? $right[0]['dt'] : $all[0]['dt'] - 86400);
                $first = Carbon::createFromTimestamp($time)->setTimezone($user->schedule->utc);
      			/*
                Telegram::sendMessage([
                    'chat_id' => $user->chat_id,
                    'text' => Carbon::createFromTimestamp($entry['dt'])->addSeconds($offset)->format('d-m-Y') . ' | ' .  $cacheAimDate->format('d-m-Y')
            	]);
      			*/
                $text = '<strong>' . $first->format('d-m-Y') . '</strong>' . "\n";
                foreach ($right as $entry) {
                    $temp = (((int)$entry['main']['temp_min'] + (int)$entry['main']['temp_max']) / 2);
                    $text .= ($user->weather->units == 'metric' ? Carbon::createFromTimestamp($entry['dt'])->setTimezone($user->schedule->utc)->format('H:i') : Carbon::createFromTimestamp($entry['dt'])->setTimezone($user->schedule->utc)->format('H:i A')) . ' ' . (Helper::sign($temp) == 1 ? '+' : '-') . $temp . ' ';
                    $text .= ' ' . $locale->getString($entry['weather'][0]['description']) . "\n";
                    if (Carbon::createFromTimestamp($entry['dt'])->setTimezone($user->schedule->utc)->format('H:i') == '00:00')
                  	  break;
                }

                Telegram::sendMessage([
                    'chat_id' => $user->chat_id,
                    'text' => $text,
                    'parse_mode' => 'html',
                    'disable_notification' => true,
                    'reply_markup' => Keyboard::make()
                        ->inline()
                        ->row(
                            Keyboard::inlineButton(['text' => '>' . $first->format('d/m') . '<', 'callback_data' => 'null']),
                            Keyboard::inlineButton(['text' => $first->addDay()->format('d/m'), 'callback_data' => 'weather 2']),
                            Keyboard::inlineButton(['text' => $first->addDay()->format('d/m'), 'callback_data' => 'weather 3']),
                            Keyboard::inlineButton(['text' => $first->addDay()->format('d/m'), 'callback_data' => 'weather 4']),
                            Keyboard::inlineButton(['text' => $first->addDay()->format('d/m'), 'callback_data' => 'weather 5'])
                        )
                ]);
            }
        }


    }

    static public function scrollMessage(User $user, int $messageId, int $callbackId, int $page)
    {
        $locale = app(Localize::class);
        $cache = \App\WeatherCache::where('location', $user->weather->location)->where('units', $user->weather->units)->get();
        $response = '';
        if ($cache->isNotEmpty()) {
            $cache = $cache[0];
            $response = $cache->content;
        } else {
            Telegram::editMessageText([
                'chat_id' => $user->chat_id,
                'message_id' => $messageId,
                'text' => $locale->getString("weather_delivery_CacheEmpty"),
                'parse_mode' => 'html',
                'disable_notification' => true,
                'reply_markup' => Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton(['text' => '-', 'callback_data' => 'null']),
                        Keyboard::inlineButton(['text' => '-', 'callback_data' => 'null']),
                        Keyboard::inlineButton(['text' => '-', 'callback_data' => 'null']),
                        Keyboard::inlineButton(['text' => '-', 'callback_data' => 'null']),
                        Keyboard::inlineButton(['text' => '-', 'callback_data' => 'null'])
                    )
            ]);
            $user->update(['function' => null, 'function_state' => null]);
            return false;
        }

        $all = json_decode($response, true);

		$offset = Carbon::now()->timezone($user->schedule->utc)->format('Z');
        $cacheBeginDate = Carbon::createFromTimestamp($all[0]['dt'])->startOfDay();
        $cacheAimDate = $cacheBeginDate->addDays($page - 1);
		$aimTime = Carbon::now()->timezone($user->schedule->utc);
        $right = array();
        
        foreach ($all as $entry) {
      		$entryCarbon = Carbon::createFromTimestamp($entry['dt'])->addSeconds($offset);
      	//	Telegram::sendMessage([
	//			'chat_id' => $user->chat_id,
//				'text' =>  $entryCarbon->format('H') . '>=' . $aimTime->format('H')
//			]);
      		if($entryCarbon->format('d-m-Y') == $cacheAimDate->format('d-m-Y') ) {
//      					Telegram::sendMessage([
//				'chat_id' => $user->chat_id,
//				'text' => 'Equals ' . $page
//			]);
      			if($page == 1) {
      				if($entryCarbon->format('H') >= $aimTime->format('H'))
      					$right[] = $entry;
      			 } else {
//      				Telegram::sendMessage([
//						'chat_id' => $user->chat_id,
//						'text' => 'Page NOT 1'
//					]);
      				$right[] = $entry;
      			}
      		}
        }

		$first = Carbon::now();
		if (!isset($right[0])) {
			$time = $all[0]['dt'] - 86400;
			$first = Carbon::createFromTimestamp($time)->setTimezone($user->schedule->utc);
//			Telegram::sendMessage([
//				'chat_id' => $user->chat_id,
//				'text' => 'Not set'
//			]);
		} else {
			$time = $right[0]['dt'];
			$first = Carbon::createFromTimestamp($time)->setTimezone($user->schedule->utc)->subDays($page - 1);
		}

		$text = '<strong>'  .   Carbon::createFromTimestamp($time)->setTimezone($user->schedule->utc)->format('d-m-Y')  . '</strong>' . "\n";
        foreach ($right as $entry) {
            $temp = (((int)$entry['main']['temp_min'] + (int)$entry['main']['temp_max']) / 2);
            $text .= ($user->weather->units == 'metric' ? Carbon::createFromTimestamp($entry['dt'])->setTimezone($user->schedule->utc)->format('H:i') : Carbon::createFromTimestamp($entry['dt'])->format('H:i A')) . ' ' . (Helper::sign($temp) == 1 ? '+' : '-') . $temp . ' ';
            $text .= ' ' . $locale->getString($entry['weather'][0]['description']) . "\n";
        }

//        $first = Carbon::createFromTimestamp($time)->setTimezone($user->schedule->utc)->subDays($page - 1);

        Telegram::answerCallbackQuery([
            'callback_query_id' => $callbackId
        ]);
//        if ((Carbon::createFromTimestamp($all[0]['dt'])->addSeconds($offset)->format('d-m-Y') != $cacheBeginDate->format('d-m-Y'))
//      		$t = ();

        Telegram::editMessageText([
            'chat_id' => $user->chat_id,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'html',
            'disable_notification' => true,
            'reply_markup' => Keyboard::make()
                ->inline()
                ->row(
                    ($page == 1 ? Keyboard::inlineButton(['text' => '>' . $first->format('d/m') . '<', 'callback_data' => 'null']) : Keyboard::inlineButton(['text' =>  $first->format('d/m'), 'callback_data' => 'weather 1'])),
                    ($page == 2 ? Keyboard::inlineButton(['text' => '>' . $first->addDay()->format('d/m') . '<', 'callback_data' => 'null']) : Keyboard::inlineButton(['text' =>  $first->addDay()->format('d/m') , 'callback_data' => 'weather 2'])),
                    ($page == 3 ? Keyboard::inlineButton(['text' => '>' . $first->addDay()->format('d/m') . '<', 'callback_data' => 'null']) : Keyboard::inlineButton(['text' =>  $first->addDay()->format('d/m') , 'callback_data' => 'weather 3'])),
                    ($page == 4 ? Keyboard::inlineButton(['text' => '>' . $first->addDay()->format('d/m') . '<', 'callback_data' => 'null']) : Keyboard::inlineButton(['text' =>  $first->addDay()->format('d/m') , 'callback_data' => 'weather 4'])),
                    ($page == 5 ? Keyboard::inlineButton(['text' => '>' . $first->addDay()->format('d/m') . '<', 'callback_data' => 'null']) : Keyboard::inlineButton(['text' =>  $first->addDay()->format('d/m') , 'callback_data' => 'weather 5']))
                )
        ]);
        $user->update(['function' => null, 'function_state' => null]);

        return true;
    }


    static public function fetch($location, $units, $lat = 0, $lon = 0)
    {
        $locale = app(Localize::class);
        $weather = WeatherCache::where('location', $location)->where('units', $units)->get();
        if ($weather->isEmpty()) {
            $endpoint = '';
            if ($lat != 0 && $lon != 0) {
                $endpoint = "http://api.openweathermap.org/data/2.5/forecast?lat={LAT}&lon={LON}&appid={API_KEY}&units={UNITS}";
                $endpoint = str_replace("{LON}", $lon, $endpoint);
                $endpoint = str_replace("{LAT}", $lat, $endpoint);
            } else {
                $endpoint = "http://api.openweathermap.org/data/2.5/forecast?q={LOCATION}&appid={API_KEY}&units={UNITS}";
                $endpoint = str_replace("{LOCATION}", $location, $endpoint);
            }
//            $endpoint = "http://api.openweathermap.org/data/2.5/forecast?q={LOCATION}&appid={API_KEY}&units={UNITS}";
            $endpoint = str_replace("{API_KEY}", env('WEATHER_API_TOKEN'), $endpoint);
//            $endpoint = str_replace("{LOCATION}", $location, $endpoint);
            $endpoint = str_replace("{UNITS}", $units, $endpoint);

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

            if (isset($response['list']) && !empty($response['list'])) {
                WeatherCache::create([
                    'location' => $location,
                    'units' => $units,
                    'content' => json_encode($response['list'])
                ]);
                return true;
            } else
                return false;
        } else
            return true;
    }

}