<?php
require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Calendar API PHP Quickstart');
    $client->setScopes(Google_Service_Calendar::CALENDAR_READONLY);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

if (empty($priceHour = $argv[1])) 
{
    $priceHour = 29.75;
}
// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Calendar($client);

$calendarList = $service->calendarList->listCalendarList();

// while(true) {
//   foreach ($calendarList->getItems() as $calendarListEntry) {
//     echo $calendarListEntry->getId() . ' - ' .  $calendarListEntry->getSummary() . PHP_EOL;
    
    // Print the next 10 events on the user's calendar.
    $calendarId = '632obrok1tgp230rta4q44vub4@group.calendar.google.com';
    // $calendarId = $calendarListEntry->getId();
    // 
    $dateMin = new DateTime('first day of this month');

    $dateMax = new DateTime('first day of this month');
    $dateMax->add(date_interval_create_from_date_string('1 months'));

    $optParams = array(
      // 'maxResults' => 10,
      'orderBy' => 'startTime',
      'singleEvents' => true,
      'timeMin' => $dateMin->format('c'),
      'timeMax' => $dateMax->format('c')
    );

    printf("Date from %s to %s \n", $dateMin->format("d-m-Y h:i:sa"), $dateMax->format("d-m-Y h:i:sa"));

    $results = $service->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();
 
    if (empty($events)) {
        print "No upcoming events found.\n";
    } else {
        print "Upcoming events:\n";
        $totalTime = 0;
        foreach ($events as $event) {
            if (strrpos($event->summary, 'Incidencia') !== false) {
                continue;
            }

            $start = $event->start->dateTime;

            if (empty($start)) {
                $start = $event->start->date;
            }

            $start_date = new DateTime($start);
            $since_start = $start_date->diff(new DateTime($event->end->dateTime));
            $end_date = new Datetime($event->end->dateTime);

            printf("(%s - %s) %s - %s\n", 
                $start_date->format('d/m/Y H:i'), 
                $end_date->format('d/m/Y H:i'), 
                $event->getSummary(), 
                $since_start->h . ':' . $since_start->i);

            $totalTime += $since_start->h + $since_start->i/60;
        }
        printf("Total time: %s\n", $totalTime);
    }  
    
    if ( ! empty($events)) {
        print "Day/hours:\n";
        $dayHours = [];
        $totalTime = 0;
        foreach ($events as $event) {
            if (strrpos($event->summary, 'Incidencia') !== false) 
            {
                continue;
            }

            $start = $event->start->dateTime;

            if (empty($start)) {
                $start = $event->start->date;
            }

            $start_date = new DateTime($start);

            $since_start = $start_date->diff(new DateTime($event->end->dateTime));
            $evenDuration = $since_start->h * 60 + $since_start->i;
            $day = $start_date->format('d/m/Y');

            if ( ! isset($dayHours[$day])) 
            {
                $dayHours[$day] = $evenDuration;
            }
            else
            {
                $dayHours[$day] += $evenDuration;
            }

            $totalTime += $evenDuration;
        }

        foreach ($dayHours as $day => $minutes) {
            printf("%s: %s (%s€)\n", 
                $day, 
                convertToHoursMins($minutes, $format = '%02d:%02d'), 
                round($minutes * $priceHour / 60, 2)
            );
        }
        printf("-----------------\n");
        printf("Total time: %s\n", convertToHoursMins($totalTime, $format = '%02d:%02d'));

        printf("€/hour: %s€\n", $priceHour);
        printf("Total incoming: %s€\n", round($totalTime * $priceHour / 60, 2));

    }  
 
function convertToHoursMins($time, $format = '%02d:%02d') {
    if ($time < 1) {
        return;
    }
    $hours = floor($time / 60);
    $minutes = ($time % 60);
    return sprintf($format, $hours, $minutes);
}

//     echo '---------------------------------------------------------' . PHP_EOL;  
//   }
//   $pageToken = $calendarList->getNextPageToken();
//   if ($pageToken) {
//     $optParams = array('pageToken' => $pageToken);
//     $calendarList = $service->calendarList->listCalendarList($optParams);
//   } else {
//     break;
//   }
// }
