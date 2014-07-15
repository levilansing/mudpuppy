<?php

namespace App;
use Model\BrowserSession;
use Mudpuppy\App;
use Mudpuppy\MudpuppyException;

/**
 * Database driven Sessions
 */
class DatabaseSessionHandler {
	/** @var BrowserSession */
	private $session = null;

	public function __construct() {
		// Set handler to override SESSION
		session_set_save_handler(
			array($this, "open"),
			array($this, "close"),
			array($this, "read"),
			array($this, "write"),
			array($this, "destroy"),
			array($this, "garbageCollect")
		);
	}

	public function open() {
		return App::getDBO() != null;
	}

	public function close() {
		return true;
	}

	public function read($id) {
		App::getDBO()->doNotLogNextQuery();
		$this->session = BrowserSession::fetchOne(['sessionId' => $id]);
		if ($this->session) {
			return $this->session->data;
		}
		return '';
	}

	public function write($id, $data) {
		if (!$this->session) {
			$this->session = new BrowserSession();
			$this->session->sessionId = $id;
		}
		if ($id != $this->session->sessionId) {
			throw new MudpuppyException('Session ID does not match original session opened');
		}
		$this->session->data = $data;
		$this->session->lastAccessed = time();
		App::getDBO()->doNotLogNextQuery();
		$this->session->save();
		return true;
	}

	public function destroy($id) {
		BrowserSession::deleteSession($id);
		return true;
	}

	public function garbageCollect($oldest) {
		BrowserSession::deleteSessionsOlderThan($oldest);
	}
}