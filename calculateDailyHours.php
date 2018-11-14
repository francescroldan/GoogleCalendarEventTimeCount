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
    $client->setScopes(Google_Service_Calendar::CALENDAR);
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


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Calendar($client);

$calendarList = $service->calendarList->listCalendarList();

require __DIR__ . '/calendars.php';

$dateMin = new DateTime(date('Y-m-01 00:00:00')); 
$dateMax = new DateTime(date('Y-m-t 23:59:59'));

$optParams = array(
  // 'maxResults' => 10,
  'orderBy' => 'startTime',
  'singleEvents' => true,
  'timeMin' => $dateMin->format('c'),
  'timeMax' => $dateMax->format('c')
);

printf("Date from %s to %s \n", $dateMin->format("d-m-Y | h:i:sa"), $dateMax->format("d-m-Y | h:i:sa"));

$results = $service->events->listEvents($calendarId, $optParams);
$events = $results->getItems();
 
if ( ! empty($events)) {
    print "Day/hours:\n";
    $dayHours = [];
    $totalTime = 0;
    foreach ($events as $event) 
    {
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
        $day = $start_date->format('Y-m-d');

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
    print_r($dayHours);

    foreach ($dayHours as $day => $minutes) 
    {
        $eventParams = array(
          'summary' => 'Today working hours: ' . convertToHoursMins($minutes, $format = '%02d:%02d') . 'h',
          // 'location' => '800 Howard St., San Francisco, CA 94103',
          // 'description' => 'A chance to hear more about Google\'s developer products.',
          'start' => array(
            'dateTime' => $day,
            'timeZone' => 'Europe/Madrid',
          ),
          // 'end' => array(
          //   'dateTime' => '2015-05-28T17:00:00-07:00',
          //   'timeZone' => 'America/Los_Angeles',
          // ),
          // 'recurrence' => array(
          //   'RRULE:FREQ=DAILY;COUNT=2'
          // ),
          // 'attendees' => array(
          //   array('email' => 'lpage@example.com'),
          //   array('email' => 'sbrin@example.com'),
          // ),
          // 'reminders' => array(
          //   'useDefault' => FALSE,
          //   'overrides' => array(
          //     array('method' => 'email', 'minutes' => 24 * 60),
          //     array('method' => 'popup', 'minutes' => 10),
          //   ),
          // ),
        );

        $event = new Google_Service_Calendar_Event($eventParams);

        $calendarId = $calendars['Horas Autónomo'];
        $event = $service->events->insert($calendarId, $event);
        // printf('Event created: %s\n', $event->htmlLink);

        printf("%s: %s\n", 
            $day, 
            convertToHoursMins($minutes, $format = '%02d:%02d')
        );
    }
    printf("-----------------\n");
    printf("Total time: %s\n", convertToHoursMins($totalTime, $format = '%02d:%02d'));
    $priceHour = 29.75;
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

