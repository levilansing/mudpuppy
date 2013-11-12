<?php
/**
 * @author Levi Lansing
 * Created 8/31/13
 */
class MessagesController extends Controller {

    public function getRequiredPermissions() {
        return array();
    }

    public function action_check() {
        return App::readMessages();
    }


}