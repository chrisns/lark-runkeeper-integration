<?php
$username="INSERT LARK USERNAME";
$password="INSERT LARK PASSWORD";
define("LARK_ENTRYPOINT", "https://my.lark.com/portal/");

class sleep {
  private $ch;
  public $sleepdata;
  function __construct($username, $password, $startdate = NULL) {
    $this->ch = curl_init();
    curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
    curl_setopt($this->ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($this->ch, CURLOPT_FORBID_REUSE, 0);
    curl_setopt($this->ch, CURLOPT_COOKIESESSION, 1);
    curl_setopt($this->ch, CURLOPT_AUTOREFERER, 1);
    // curl_setopt ($this->ch, CURLOPT_VERBOSE, 1);
    curl_setopt($this->ch, CURLOPT_HEADER, 1);
    $login = $this->getLarklogin($username, $password);
        // $startdate = strtotime("June 15, 2013");

    if (!$startdate) {
      preg_match("/(?<=Since ).*(?=\<)/", $login, $sinceSeach);
      $startdate = strtotime(array_pop($sinceSeach));
    }

    $dates = range($startdate, time(), 60*60*24);
    foreach($dates as $date) {
      $sleepdata = $this->getLarkSleepData($date);
      if($sleepdata) {
        $this->sleepdata[$date] = $sleepdata;
      }
    }
  }
  function getLarkSleepData($date) {
    $url = "dailyReport/" . $date . "000.json";
        curl_setopt ($this->ch, CURLOPT_HTTPGET, 1);

    curl_setopt ($this->ch, CURLOPT_URL, LARK_ENTRYPOINT . $url);
    curl_setopt($this->ch, CURLOPT_COOKIE,  $this->cookies);
    curl_setopt($this->ch, CURLOPT_HEADER, 0);
    $response = json_decode(curl_exec($this->ch));
    $responseCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

    if ($responseCode != 200) {
      throw new Exception("Request Fail $responseCode", 1);
    }
    if (empty($response->report->data)) {
      print "no sleep data for:" . date("r", $date) . "\n";
      return FALSE;
    }
    print "retrieved sleep data for:" . date("r", $date) . "\n";
    return $response->report;
  }

  function getLarklogin($username, $password) {
    $url = "j_spring_security_check";

    $postdata = "j_username=" . $username . "&j_password=" . $password;

    curl_setopt ($this->ch, CURLOPT_URL, LARK_ENTRYPOINT . $url);
    curl_setopt ($this->ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt ($this->ch, CURLOPT_POST, 1);

    $response = curl_exec ($this->ch);
    $responseCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

    if ($responseCode != 200) {
      throw new Exception("Login Fail $responseCode", 1);
    }
    preg_match_all('|Set-Cookie: (.*);|U', $response, $results);
    $this->cookies = implode(';', $results[1]);
    return $response;
  }
  function close() {
    curl_close($this->ch);
  }
}

//
// $sl->close();
print "<pre>";

define('YAMLPATH', 'yaml/');
define('RUNKEEPERAPIPATH', 'runkeeperAPI/');
define('CONFIGPATH', 'config/');

require(YAMLPATH.'lib/sfYamlParser.php');
require(RUNKEEPERAPIPATH.'lib/runkeeperAPI.class.php');

/* API initialization */
$rkAPI = new runkeeperAPI('config.yml');
if ($rkAPI->api_created == false) {
  throw new Exception($rkAPI->api_last_error . print_r($rkAPI->request_log, 1), 1);
}

/* Generate link to allow user to connect to Runkeeper and to allow your app*/
if (!$rkAPI->doRunkeeperRequest('Profile','Read') && !$_GET['code']) {
  header("Location:". $rkAPI->connectRunkeeperButtonUrl());
  exit();
}

/* After connecting to Runkeeper and allowing your app, user is redirected to redirect_uri param (as specified in YAML config file) with $_GET parameter "code" */
if ($_GET['code']) {
  $auth_code = $_GET['code'];
  if ($rkAPI->getRunkeeperToken($auth_code) == false) {
    throw new Exception($rkAPI->api_last_error . print_r($rkAPI->request_log, 1), 1);
  }
  else {
    print "Logged in as :" . $rkAPI->doRunkeeperRequest('Profile','Read')->name . "\n";

    /* Do a "Read" request on "FitnessActivities" interface => return all fields available for this Interface or false if request fails */
    $existingSleep = $rkAPI->doRunkeeperRequest('SleepFeed','Read', null, null, array("pageSize" => 1));
    if ($existingSleep) {
      print "last logged sleep in runkeeper:" . $existingSleep->items[0]->timestamp . "\n";
    }
    else {
      throw new Exception($rkAPI->api_last_error . print_r($rkAPI->request_log, 1), 1);
    }
    $startdate = strtotime(array_pop($existingSleep->items)->timestamp) + ((60*60)*24);
    $sl = new sleep($username, $password, $startdate);
    foreach($sl->sleepdata as $timestamp => $sleep) {
      if (empty($sleep)) {
        continue;
      }
      $newsleep->timestamp = date("r", $timestamp);
      $newsleep->total_sleep = $sleep->minutesAsleep;
      $newsleep->times_woken = $sleep->timesAwakened;
      $rkCreateActivity = $rkAPI->doRunkeeperRequest('NewSleep','Create',$newsleep);
      if ($rkCreateActivity) {
        print "Logged sleep for " . $newsleep->timestamp . "\n";
      }
      else {
        throw new Exception($rkAPI->api_last_error . print_r($rkAPI->request_log, 1), 1);
      }
    }
  }
}
print "</pre>";
