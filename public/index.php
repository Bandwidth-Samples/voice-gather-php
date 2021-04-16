<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$BW_ACCOUNT_ID = getenv("BW_ACCOUNT_ID");
$BW_USERNANME = getenv("BW_USERNAME");
$BW_PASSWORD = getenv("BW_PASSWORD");
$BW_VOICE_APPLICATION_ID = getenv("BW_VOICE_APPLICATION_ID");
$BASE_CALLBACK_URL = getenv("BASE_CALLBACK_URL");

$config = new BandwidthLib\Configuration(
    array(
        "voiceBasicAuthUserName" => $BW_USERNANME,
        "voiceBasicAuthPassword" => $BW_PASSWORD
    )
);

// Instantiate Bandwidth Client
$client = new BandwidthLib\BandwidthClient($config);

// Instantiate App
$app = AppFactory::create();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

$voice_client = $client->getVoice()->getClient();

$app->post('/outboundCall', function (Request $request, Response $response) {
    // POST with to, from, and tag creates outbound call
    global $BW_ACCOUNT_ID, $BW_VOICE_APPLICATION_ID, $BASE_CALLBACK_URL, $voice_client;
    $data = $request->getParsedBody();
    $body = new BandwidthLib\Voice\Models\ApiCreateCallRequest();
    $body->from = $data['from'];
    $body->to = $data['to'];
    $body->answerUrl = $BASE_CALLBACK_URL . "/voiceCallback";;
    $body->applicationId = $BW_VOICE_APPLICATION_ID;

    try {
        $apiResponse = $voice_client->createCall($BW_ACCOUNT_ID, $body);
        $callId = $apiResponse->getResult()->callId;
        $response->getBody()->write('{"callId": "'.$callId.'"}');
        return $response->withStatus(201)
          ->withHeader('Content-Type', 'application/json');
    } catch (BandwidthLib\APIException $e) {
        $response->getBody()->write($e);
        return $response->withStatus(400);
    }
});

$app->post('/voiceCallback', function (Request $request, Response $response) {
    // Return Gather bxml
    $speakSentence = new BandwidthLib\Voice\Bxml\SpeakSentence("Press one for option one, Press two for option two. Then, press pound");
    $speakSentence->voice("kate");

    $gather = new BandwidthLib\Voice\Bxml\Gather();
    $gather->gatherUrl("/gatherCallback");
    $gather->terminatingDigits("#");
    $gather->maxDigits(1);
    $gather->firstDigitTimeout(10);
    $gather->speakSentence($speakSentence);

    $bxmlResponse = new BandwidthLib\Voice\Bxml\Response();
    $bxmlResponse->addVerb($gather);

    $bxml = $bxmlResponse->toBxml();
    $response = $response->withStatus(200)->withHeader('Content-Type', 'application/xml');
    $response->getBody()->write($bxml);
    return $response;

});

$app->post('/gatherCallback', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    if ($data['digits'] == 1){
      $speakSentence = new BandwidthLib\Voice\Bxml\SpeakSentence("You have chosen option one, thank you.");
    } else if ($data['digits'] == 2){
      $speakSentence = new BandwidthLib\Voice\Bxml\SpeakSentence("You have chosen option two, thank you.");
    } else {
      $speakSentence = new BandwidthLib\Voice\Bxml\SpeakSentence("You have chosen an invalid option.");
    }
    $speakSentence->voice("julie");

    $bxmlResponse = new BandwidthLib\Voice\Bxml\Response();
    $bxmlResponse->addVerb($speakSentence);

    $bxml = $bxmlResponse->toBxml();
    $response = $response->withStatus(200)->withHeader('Content-Type', 'application/xml');
    $response->getBody()->write($bxml);
    return $response;

});

$app->run();
