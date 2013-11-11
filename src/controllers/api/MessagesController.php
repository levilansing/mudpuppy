<?php
/**
 * @author Levi Lansing
 * Created 8/31/13
 */
class MessagesModule extends Module {

    public function getRequiredPermissions($method, $input) {
        return array();
    }

    public function action_check() {
        return App::readMessages();
    }


}