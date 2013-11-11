<?php
/**
 * @author Levi Lansing
 * Created 8/28/13
 */
defined('MUDPUPPY') or die('Restricted');

class HomeController extends Controller {
    public static $events;
    public static $eventCount;
    public static $pendingRequestCount = 0;
    public static $usersEvents = array();

    public function getRequiredPermissions() {
        return array();
    }

    public function processPost() {

    }

    public function render() {
        $pageSize = 0;
        if (!self::$events) {
            $startDate = Request::get('year') ? strtotime('1/1/'.Request::get('year')) : null;
            $endDate = Request::get('year') ? strtotime('12/31/'.Request::get('year')) : null;
            $userId = Request::get('show') == 'mine' ? Security::getUser()->id : null;
            if (!$startDate) {
                $startDate = strtotime('-1 year');
            }
            self::$events = Event::listByDateWithOptions($startDate, $endDate, $userId, Request::get('p',0)*$pageSize, $pageSize);
            self::$eventCount = Event::getCount();
            self::$usersEvents = Attendee::getEventIdListForUserId(Security::getUser()->id);
        }
        include('views/home.php');
    }
}