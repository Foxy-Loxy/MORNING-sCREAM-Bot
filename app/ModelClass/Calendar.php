<?php


namespace App\ModelClass;

use App\Helpers\GoogleApiHelper;
use App\Helpers\Localize;
use App\NewsCache;
use App\User;
use Carbon\Carbon;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Exceptions;

class Calendar
{
    // TODO Finish class
    static public function scheduleCall(User $user)
    {
        $locale = app(Localize::class);

        $menuKeyboard = Keyboard::make([
            'keyboard' => [
                [$locale->getString('calendar_menu_Auth')],
                [$locale->getString('cancel')]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        Telegram::sendMessage([
            'chat_id' => $user->chat_id,
            'text' => $locale->getString('calendar_menu_Enter'),
            'reply_markup' => $menuKeyboard
        ]);
        //Since only operation available for this service will be
        $user->update([
            'function' => \App\Calendar::NAME,
            'function_state' => 'WAITING_FOR_CALENDAR_MENU'
        ]);



    }

    static public function scheduleConfirm(User $user, string $input, Keyboard $exitKbd)
    {
        $locale = app(Localize::class);

        $menuKeyboard = Keyboard::make([
            'keyboard' => [
                [$locale->getString('calendar_menu_Auth')],
                [$locale->getString('calendar_menu_DeAuth')],
                [$locale->getString('cancel')]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);

        $canKeyboard = Keyboard::make([
            'keyboard' => [
                [$locale->getString('cancel')]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ]);


        if ($user->function == \App\Calendar::NAME && $user->function_state != null) {

            switch ($input) {

                case $locale->getString('cancel'):
                    $user->update([
                        'function' => null,
                        'function_state' => null
                    ]);
                    Telegram::sendMessage([
                        'chat_id' => $user->chat_id,
                        'text' => $locale->getString('canceled'),
                        'reply_markup' => $exitKbd
                    ]);
                    return false;
                    break;
            }
            switch ($user->function_state) {

                case 'PROCESSING_AUTH':
                    $client = GoogleApiHelper::getClient($user);
                    if (is_string($client)) {
                        Telegram::sendMessage([
                            'chat_id' => $user->chat_id,
                            'text' => $locale->getString('calendar_Auth_Url') . '<a href="' . $client    .'">' . $locale->getString('calendar_Auth_button') .'</a>',
                            'reply_markup' => $canKeyboard,
                            'parse_mode' => 'html'
                        ]);
                        $user->update([
                            'function_state' => 'WAITING_FOR_TOKEN'
                        ]);
                        return true;
                    } else {
                        Telegram::sendMessage([
                            'chat_id' => $user->chat_id,
                            'text' => $locale->getString('calendar_Auth_Success'),
                            'reply_markup' => $menuKeyboard,
                        ]);
                        return true;
                    }
                    break;

                case 'WAITING_FOR_CATEGORY_MENU':
                    switch ($input) {
                        case $locale->getString('calendar_menu_Auth'):
                            Telegram::sendMessage([
                                'chat_id' => $user->chat_id,
                                'text' => $locale->getString('calendar_Auth_Enter'),
                                'reply_markup' => $canKeyboard
                            ]);
                            $user->update([
                                'function_state' => 'PROCESSING_AUTH'
                            ]);
                            break;

                        case $locale->getString('calendar_menu_DeAuth'):
                            if ($user->calendar != null){
                                $user->calendar->delete();
                                Telegram::sendMessage([
                                    'chat_id' => $user->chat_id,
                                    'text' => $locale->getString('calendar_DeAuth_Success'),
                                    'reply_markup' => $menuKeyboard
                                ]);
                            } else {
                                Telegram::sendMessage([
                                    'chat_id' => $user->chat_id,
                                    'text' => $locale->getString('calendar_DeAuth_Fail'),
                                    'reply_markup' => $menuKeyboard
                                ]);
                            }

                            break;
                    }
                    break;

                case 'WAITING_FOR_CATEGORY':


                    break;

            }
        }
        return true;
    }

    static public function deliver(User $user) {

    }

}