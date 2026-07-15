<?php
namespace App\Notify;

class Edit extends \Routing_Parent implements \Routing_Interface {
	private int $notify_id = 0;
	private array $notify = [];
	private bool $can_read = false;
	private bool $can_write = false;
	private bool $can_create = false;

	/** Результат «получить из последнего сообщения Max» (один запрос POST) */
	private ?array $maxFetchParsed = null;

	public function Run($notify_id=0) {
		$this->notify_id = intval($notify_id ?? 0);
		$this->check_auth();
		$this->check_permissions();

		$this->processRequest();
		$this->get_data();
		$this->template = new \Template('Notify edit');
		$this->print_header();

		$this->print_forms();
		$this->print_javascript();
	}

	protected function handlePostData(array $data) {
		// Обработка POST данных
		if (!isset($data)) return;
		$this->save_notify($data);
	}

	private function save_notify($data) {
		if (!isset($data)) return;
		if (!$data) return;

		$this->maxFetchParsed = null;
		if (isset($data['maxFetchLastMessage']) && $data['maxFetchLastMessage'] === 'true') {
			$this->handleMaxFetchLastMessage($data);
			foreach ($data as $key => $value) {
				$this->notify[$key] = $value;
			}
			return;
		}

		$testNotify = false;
		if (isset($data['testNotify']) && $data['testNotify'] == 'true') {
			$testNotify = true;
		}
		$params = [];
		do {
			if (isset($data['id']) && trim($data['id']) != '') {
				$data['id'] = trim($data['id']);
				if ($this->notify_id != intval($data['id'])) {
					$this->error = \T::Notify_Edit_NotFound();
					break;
				}
				$params['id'] = intval($data['id']);
			}
			if (isset($data['title']) && trim($data['title']) != '') {
				$data['title'] = trim($data['title']);
				if (mb_strlen($data['title']) < 2 || mb_strlen($data['title']) > 255) {
					$this->error = \T::Notify_Edit_NameLengthError();
					break;
				}
				$params['title'] = $data['title'];
			}
			if (isset($data['type']) && trim($data['type']) != '') {
				$params['type'] = intval(trim($data['type']));
			}
			if (isset($data['status']) && trim($data['status']) != '') {
				$data['status'] = trim($data['status']);
				if ($data['status'] == 'true') {
					$data['status'] = 1;
				} else {
					$data['status'] = 0;
				}
				$params['status'] = intval($data['status']);
			} else {
				$data['status'] = 0;
				$params['status'] = 0;
			}
			if (isset($data['receiver']) && trim($data['receiver']) != '') {
				$params['receiver'] = trim($data['receiver']);
			}

			$can_change_secrets = false;
			if ($this->notify_id) {
				$can_change_secrets = \Sessions::checkPermission(\Notify::PERMISSION_SECRETS, $this->notify['id'], READ);
			} else {
				$can_change_secrets = true;
			}

			if ($can_change_secrets) {
				if (!isset($data['json_data']) || !is_array($data['json_data'])) $data['json_data'] = [];
				if ($params['type'] == \Notify::TYPE_TELEGRAM) {
					if (isset($data['json_data']['telegram_bot_token']) && $data['json_data']['telegram_bot_token'] && trim($data['json_data']['telegram_bot_token']) != '') {
						$params['json_data']['telegram_bot_token'] = trim($data['json_data']['telegram_bot_token']);
					}
				} elseif ($params['type'] == \Notify::TYPE_MATTERMOST) {
					if (isset($data['json_data']['mattermost_url']) && $data['json_data']['mattermost_url'] && trim($data['json_data']['mattermost_url']) != '') {
						$params['json_data']['mattermost_url'] = trim($data['json_data']['mattermost_url']);
					}
					if (isset($data['json_data']['mattermost_token']) && $data['json_data']['mattermost_token'] && trim($data['json_data']['mattermost_token']) != '') {
						$params['json_data']['mattermost_token'] = trim($data['json_data']['mattermost_token']);
					}
				} elseif ($params['type'] == \Notify::TYPE_GOTIFY) {
					if (isset($data['json_data']['gotify_url']) && $data['json_data']['gotify_url'] && trim($data['json_data']['gotify_url']) != '') {
						$params['json_data']['gotify_url'] = trim($data['json_data']['gotify_url']);
					}
					if (isset($data['json_data']['gotify_priority']) && $data['json_data']['gotify_priority'] && trim($data['json_data']['gotify_priority']) != '') {
						$params['json_data']['gotify_priority'] = trim($data['json_data']['gotify_priority']);
					}
				} elseif ($params['type'] == \Notify::TYPE_MAX) {
					if (isset($data['json_data']['max_token']) && $data['json_data']['max_token'] && trim($data['json_data']['max_token']) != '') {
						$params['json_data']['max_token'] = trim($data['json_data']['max_token']);
					}
				}
			}

			if ($testNotify) {
				$old_notify = [];
				if (isset($params['id']) && $params['id']) {
					if (!$this->can_read) {
						$this->error = \T::Notify_Edit_PermissionDenied();
						break;
					}
					$old_notify = \Notify::get(['id' => $params['id']]);
					if (!$old_notify) {
						$this->error = \T::Notify_Edit_NotFound();
						break;
					}
					if ($old_notify['json_data'] && is_string($old_notify['json_data'])) {
						$old_notify['json_data'] = json_decode($old_notify['json_data'], true);
					} else {
						$old_notify['json_data'] = [];
					}
				} else {
					$old_notify['json_data'] = [];
				}

				if (!isset($params['json_data']) || !is_array($params['json_data'])) {
					$params['json_data'] = $old_notify['json_data'];
				}
				if (!$params['json_data']) {
					$params['json_data'] = [];
				}
				if (!isset($params['receiver']) || !$params['receiver']) {
					$this->error = \T::Notify_Edit_TestError(0);
					break;
				}

				if (!$this->can_write) {
					$data['status'] = $old_notify['status']??0;
					$data['type'] = $old_notify['type']??0;

					$params['status'] = $data['status'];
					$params['type'] = $data['type'];
				}

				if ($params['type'] == \Notify::TYPE_TELEGRAM) {
					if (!isset($params['json_data']['telegram_bot_token']) || !$params['json_data']['telegram_bot_token']) {
						$this->error = \T::Notify_Edit_TestError(10);
						break;
					}
					try {
						$t = explode(':',$params['receiver']);
						$telegram_chat_id = $t[0];
						if (isset($t[1]) && $t[1]) {
							$telegram_thread_id = $t[1];
						} else {
							$telegram_thread_id = 0;
						}
						$bot = new \TelegramBot\Api\BotApi($params['json_data']['telegram_bot_token']);
						if (\Config::getInstance()->telegram_proxy_host) {
							$bot->setProxy((\Config::getInstance()->telegram_proxy_host).':'.(\Config::getInstance()->telegram_proxy_port), \Config::getInstance()->telegram_proxy_socks5);
							if (\Config::getInstance()->telegram_proxy_username && \Config::getInstance()->telegram_proxy_password) {
								$bot->setCurlOption(CURLOPT_PROXYUSERPWD, \Config::getInstance()->telegram_proxy_username.':'.\Config::getInstance()->telegram_proxy_password);
							}
						}
						@$bot->sendMessage(
							$telegram_chat_id,
							'Test Message '.date('Y-m-d H:i:s',time()).'',
							'HTML', true, null, null, false,
							$telegram_thread_id
						);
					} catch (\Exception $e) {
						$this->error = \T::Notify_Edit_TestError(11);
						break;
					}
					$this->success = \T::Notify_Edit_TestSuccess();
					break;
				} else if ($params['type'] == \Notify::TYPE_MATTERMOST) {
					if (!isset($params['json_data']['mattermost_url']) || !$params['json_data']['mattermost_url']) {
						$this->error = \T::Notify_Edit_TestError(20);
						break;
					}
					if (!isset($params['json_data']['mattermost_token']) || !$params['json_data']['mattermost_token']) {
						$this->error = \T::Notify_Edit_TestError(21);
						break;
					}
					try {
						$mattermost = new \Mattermost($params['json_data']['mattermost_url'], $params['json_data']['mattermost_token']);
						if (strpos($params['receiver'], '@') !== false) {
							$mattermost->sendMessageByEmail(
								$params['receiver'],
								'Test Message '.date('Y-m-d H:i:s',time()).'',
							);
						} else {
							$mattermost->sendMessage(
								$params['receiver'],
								'Test Message '.date('Y-m-d H:i:s',time()).'',
							);
						}
					} catch (\Exception $e) {
						$this->error = \T::Notify_Edit_TestError(22);
						break;
					}
					$this->success = \T::Notify_Edit_TestSuccess();
					break;
				} else if ($params['type'] == \Notify::TYPE_GOTIFY) {
					if (!isset($params['json_data']['gotify_url']) || !$params['json_data']['gotify_url']) {
						$this->error = \T::Notify_Edit_TestError(30);
						break;
					}
					try {
						$curl = new \AKEB\CurlGet(($params['json_data']['gotify_url']??'').'/message?token='.($params['receiver']??''), [], [], ['Content-Type: application/json;charset=utf-8']);
						$curl->setBody(json_encode([
							'message' => 'Test Message '.date('Y-m-d H:i:s',time()).'',
							'priority' => intval($params['json_data']['gotify_priority']??0),
						]));
						$curl->exec();
					} catch (\Exception $e) {
						$this->error = \T::Notify_Edit_TestError(31);
						break;
					}
					$this->success = \T::Notify_Edit_TestSuccess();
					break;
				} else if ($params['type'] == \Notify::TYPE_MAX) {
					if (!isset($params['json_data']['max_token']) || !$params['json_data']['max_token']) {
						$this->error = \T::Notify_Edit_TestError(40);
						break;
					}
					$maxReceiverStr = trim((string) ($params['receiver'] ?? ''));
					if ($maxReceiverStr === '' || !preg_match('/^-?\d+$/', $maxReceiverStr)
						|| $maxReceiverStr === '0' || $maxReceiverStr === '-0') {
						$this->error = \T::Notify_Edit_TestError(42);
						break;
					}
					try {
						$ok = \MaxMessenger::send(
							$params['json_data']['max_token'],
							$maxReceiverStr,
							'Test Message '.date('Y-m-d H:i:s', time()),
							'html'
						);
						if (!$ok) {
							$this->error = \T::Notify_Edit_TestError(41);
							break;
						}
					} catch (\Exception $e) {
						$this->error = \T::Notify_Edit_TestError(41);
						break;
					}
					$this->success = \T::Notify_Edit_TestSuccess();
					break;
				}
				$this->error = \T::Notify_Edit_TestError(1);
				break;
			}

			$old_notify = [];
			if (isset($params['id']) && $params['id']) {
				if (!\Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $params['id'], WRITE)) {
					$this->error = \T::Notify_Edit_PermissionDenied();
					break;
				}
				$old_notify = \Notify::get(['id' => $params['id']]);
				if (!$old_notify) {
					$this->error = \T::Notify_Edit_NotFound();
					break;
				}
				if ($old_notify['json_data'] && is_string($old_notify['json_data'])) {
					$old_notify['json_data'] = json_decode($old_notify['json_data'], true);
				} else {
					$old_notify['json_data'] = [];
				}
				foreach($params as $k=>$v) {
					if ($old_notify[$k] == $v) {
						unset($params[$k]);
					}
				}
				if (!$params) {
					$this->success = \T::Notify_Edit_NotingChanged();
					break;
				}
				$params['id'] = $old_notify['id'];
			} else {
				if (!\Sessions::checkPermission(\Notify::PERMISSION_CREATE, 0, WRITE)) {
					$this->error = \T::Notify_Create_PermissionDenied();
					break;
				}
				$params['create_time'] = time();
			}
			$params['update_time'] = time();

			if (isset($params['json_data'])) {
				$params['json_data'] = json_encode($params['json_data']);
			}

			$notify_id = 0;
			if (isset($params['id']) && $params['id']) {
				// Update
				$params['_mode'] = \DB\Common::CSMODE_UPDATE;
				$notify_id = \Notify::save($params);
				$new_notify = \Notify::get(['id' => $notify_id]);
				\Logs::update_log(\Notify::LOGS_OBJECT, $params['id'], $old_notify, $new_notify,[
					'_save_fields' => ['id', 'title'],
				]);
				if (!$new_notify) {
					$this->error = \T::Notify_Edit_NotFound();
					break;
				}
			} else {
				// Create
				$params['_mode'] = \DB\Common::CSMODE_INSERT;
				$notify_id = \Notify::save($params);
				$notify = \Notify::get(['id' => $notify_id]);
				$log_id = \Logs::create_log(\Notify::LOGS_OBJECT, $notify_id, $notify);
				if (!$notify) {
					$this->error = \T::Notify_Edit_NotFound();
					break;
				}

				if (!\Sessions::in_group(\Groups::ADMIN_GROUP_ID, intval(\Sessions::currentUser()['id']))) {
					$permissions = \Notify::permissions_hash();
					foreach($permissions as $permission => $_) {
						$ObjectPermissions = [
							'object' => 'user',
							'object_id' => intval(\Sessions::currentUser()['id']),
							'subject' => $permission,
							'subject_id' => $notify_id,
							READ => 1,
							WRITE => 1,
							DELETE => 1,
							ACCESS_READ => 1,
							ACCESS_WRITE => 1,
							ACCESS_CHANGE => 1,
							'create_time' => time(),
							'update_time' => time(),
							'_mode' => \DB\Common::CSMODE_INSERT,
						];
						$ObjectPermissions['id'] = \ObjectPermissions::save($ObjectPermissions);
						$log_id = \Logs::create_log(\ObjectPermissions::LOGS_OBJECT, $ObjectPermissions['id'], $ObjectPermissions);
						\Logs::add_tag($log_id, \Notify::LOGS_OBJECT, $notify_id);
					}
				}
			}
			if ($notify_id) {
				common_redirect('/admin/notify/edit/' . $notify_id . '/');
			} else {
				common_redirect('/admin/notify/');
			}
		} while(false);
		foreach($data as $key => $value) {
			$this->notify[$key] = $value;
		}
	}

	private function check_permissions() {
		if ($this->notify_id) {
			$this->notify = \Notify::get(['id' => $this->notify_id]);
			if (!$this->notify) {
				common_redirect('/admin/notify/');
			}
			$this->can_read = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $this->notify_id, READ);
			$this->can_write = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $this->notify_id, WRITE);
			if (!$this->can_read) {
				e403();
			}
		} else {
			$this->can_read = \Sessions::checkPermission(\Notify::PERMISSION_ACCESS, -1, READ);
			$this->can_create = \Sessions::checkPermission(\Notify::PERMISSION_CREATE, 0, WRITE);
			$this->can_write = $this->can_create;
			if (!$this->can_read && !$this->can_create) {
				e403();
			}
		}
	}

	private function get_data() {
		if (!isset($this->notify['id'])) $this->notify['id'] = '';

		if ($this->notify['id']) {
			$this->notify['create_date'] = (
				isset($this->notify['create_time']) && $this->notify['create_time'] > 0 ?
				date('Y-m-d H:i:s', $this->notify['create_time']) : ''
			);
			$this->notify['update_date'] = (
				isset($this->notify['update_time']) && $this->notify['update_time'] > 0 ?
				date('Y-m-d H:i:s', $this->notify['update_time']) : ''
			);
			$this->notify['json_data'] = (
				isset($this->notify['json_data']) && is_string($this->notify['json_data']) ?
					json_decode($this->notify['json_data'], true) :
					(
						isset($this->notify['json_data']) && is_array($this->notify['json_data']) ?
						$this->notify['json_data'] :
						[]
					)
			);
		} else {
			$this->notify['status'] ??= 1;
		}
	}

	private function handleMaxFetchLastMessage(array $data): void {
		$type = intval($data['type'] ?? 0);
		if ($type !== \Notify::TYPE_MAX) {
			$this->error = \T::Notify_Edit_MaxFetchWrongType();
			return;
		}
		$token = '';
		if (isset($data['json_data']['max_token']) && trim((string) $data['json_data']['max_token']) !== '') {
			$token = trim((string) $data['json_data']['max_token']);
		}
		if ($token === '' && $this->notify_id) {
			if (!\Sessions::checkPermission(\Notify::PERMISSION_SECRETS, $this->notify_id, READ)) {
				$this->error = \T::Notify_Edit_MaxFetchNeedToken();
				return;
			}
			$row = \Notify::get(['id' => $this->notify_id]);
			if ($row && !empty($row['json_data'])) {
				$jd = is_string($row['json_data']) ? json_decode($row['json_data'], true) : $row['json_data'];
				if (is_array($jd) && !empty($jd['max_token'])) {
					$token = trim((string) $jd['max_token']);
				}
			}
		}
		if ($token === '') {
			$this->error = \T::Notify_Edit_MaxFetchNoToken();
			return;
		}
		if ($this->notify_id) {
			if (!\Sessions::checkPermission(\Notify::PERMISSION_ACCESS, $this->notify_id, READ)) {
				$this->error = \T::Notify_Edit_PermissionDenied();
				return;
			}
		} else {
			if (!\Sessions::checkPermission(\Notify::PERMISSION_CREATE, 0, WRITE)) {
				$this->error = \T::Notify_Create_PermissionDenied();
				return;
			}
		}

		$updatesRes = \MaxMessenger::fetchUpdates($token);
		$parsed = null;
		$chats = [];
		if ($updatesRes['ok'] && is_array($updatesRes['data'])) {
			$parsed = \MaxMessenger::parseLastMessageFromUpdates($updatesRes['data']);
			$chats = \MaxMessenger::parseChatsFromUpdates($updatesRes['data']);
		}

		$hasData = ($parsed !== null || $chats !== []);
		if ($hasData) {
			$this->maxFetchParsed = [
				'last_message' => $parsed,
				'chats' => $chats,
			];
			return;
		}
		if (!$updatesRes['ok']) {
			$this->error = \T::Notify_Edit_MaxFetchHttp(intval($updatesRes['http_code']));
			return;
		}
		$this->error = \T::Notify_Edit_MaxFetchNoData();
	}

	private function print_header() {
		?>
		<div class="float-start"><h2><i class="bi bi-bell"></i> <?=\T::Notify_PageTitle();?></h2></div>
		<div class="clearfix"></div>
		<?php
	}

	private function print_forms() {
		$secrets_show = false;
		if (!$this->notify['id']) {
			$secrets_show = true;
		} else {
			$secrets_show = \Sessions::checkPermission(\Notify::PERMISSION_SECRETS, $this->notify['id'], READ);
		}
		?>
		<div class="row d-flex justify-content-center">
			<div class="card bg-transparent col-xl-10 p-4 mt-2 mb-3">
				<div class="card-header bg-transparent"><h3>
					<?php
					if ($this->notify['id']) {
						echo \T::Notify_Edit_EditTitle($this->notify['title'], $this->notify['id']);
					} else {
						echo \T::Notify_Edit_CreateTitle();
					}
					?>
				</h3></div>
				<form class="card-body needs-validation" method="post" novalidate>
					<input type="hidden" name="id" value="<?=$this->notify['id'];?>"/>
					<?php
					echo $this->template->html_input("title", $this->notify['title']??'', \T::Notify_Edit_Title(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template?->html_select('type', \Notify::type_hash(), intval($this->notify['type']??0), \T::Notify_Edit_Type(), true,[
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_switch("status", intval($this->notify['status']??0), \T::Notify_Edit_Status(), false,[
						'readonly' => !($this->can_write || $this->can_create),
					]);
					echo $this->template->html_input("receiver", $this->notify['receiver']??'', \T::Notify_Edit_Receiver(), true, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'readonly' => !($this->can_write || $this->can_create),
					]);
					?>
					<div class="type-dependence type-<?=\Notify::TYPE_MAX;?> col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6 mb-2">
						<small class="text-muted"><?=\T::Notify_Edit_MaxReceiverHint();?></small>
					</div>
					<?php
					if ($secrets_show) {
						echo $this->template->html_input("json_data[telegram_bot_token]", $this->notify['json_data']['telegram_bot_token']??'', \T::Notify_Edit_TelegramBotToken(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'global-class' => 'type-dependence type-'.\Notify::TYPE_TELEGRAM,
							'readonly' => !($this->can_write || $this->can_create),
						]);
						echo $this->template->html_input("json_data[max_token]", $this->notify['json_data']['max_token']??'', \T::Notify_Edit_MaxToken(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'global-class' => 'type-dependence type-'.\Notify::TYPE_MAX,
							'readonly' => !($this->can_write || $this->can_create),
						]);
						echo $this->template->html_input("json_data[mattermost_url]", $this->notify['json_data']['mattermost_url']??'', \T::Notify_Edit_MattermostDomain(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'global-class' => 'type-dependence type-'.\Notify::TYPE_MATTERMOST,
							'readonly' => !($this->can_write || $this->can_create),
						]);
						echo $this->template->html_input("json_data[mattermost_token]", $this->notify['json_data']['mattermost_token']??'', \T::Notify_Edit_MattermostToken(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'global-class' => 'type-dependence type-'.\Notify::TYPE_MATTERMOST,
							'readonly' => !($this->can_write || $this->can_create),
						]);
						echo $this->template->html_input("json_data[gotify_url]", $this->notify['json_data']['gotify_url']??'', \T::Notify_Edit_GotifyServer(), false, [
							'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
							'global-class' => 'type-dependence type-'.\Notify::TYPE_GOTIFY,
							'readonly' => !($this->can_write || $this->can_create),
						]);
					}

					echo $this->template->html_input("json_data[gotify_priority]", $this->notify['json_data']['gotify_priority']??'', \T::Notify_Edit_GotifyPriority(), false, [
						'class2' => 'col-xs-12 col-sm-12 col-md-9 col-lg-8 col-xl-7 col-xxl-6',
						'global-class' => 'type-dependence type-'.\Notify::TYPE_GOTIFY,
						'readonly' => !($this->can_write || $this->can_create),
					]);

					if ($this->notify['id']) {
						echo $this->template->html_input("create_date", $this->notify['create_date']??'', \T::Notify_Edit_CreateTime(), false, ['readonly' => true]);
						echo $this->template->html_input("update_date", $this->notify['update_date']??'', \T::Notify_Edit_UpdateTime(), false, ['readonly' => true]);
					}
					?>
					<div class="d-flex flex-row-reverse flex-wrap gap-2">
						<button type="submit" class="btn btn-primary me-2" name="<?=$this->notify['id'] ? 'editNotify' : 'createNotify';?>" value="true"><?=($this->notify['id'] ? \T::Notify_Edit_ChangeButton() : \T::Notify_Edit_CreateButton());?></button>
						<button type="submit" class="btn btn-warning me-2" name="testNotify" value="true"><?=\T::Notify_Edit_TestButton();?></button>
						<button type="submit" class="btn btn-secondary me-2 type-dependence type-<?=\Notify::TYPE_MAX;?>" name="maxFetchLastMessage" value="true" formnovalidate><?=\T::Notify_Edit_MaxFetchLastButton();?></button>
					</div>
				</form>
				<?php
				if ($this->maxFetchParsed !== null) {
					$mf = $this->maxFetchParsed;
					$lm = $mf['last_message'] ?? null;
					$chats = isset($mf['chats']) && is_array($mf['chats']) ? $mf['chats'] : [];
					$blocks = [];
					if (is_array($lm)) {
						$msgRaw = (string) ($lm['message'] ?? '');
						$msgEmpty = !empty($lm['message_empty']);
						$msgLine = $msgEmpty
							? \T::Notify_Edit_MaxFetchNoMessageText()
							: \T::Notify_Edit_MaxFetchLineMessage($msgRaw);
						$prePlain = \T::Notify_Edit_MaxFetchLineChatId((string) ($lm['chat_id'] ?? ''))
							."\n".\T::Notify_Edit_MaxFetchLineUserId((string) ($lm['user_id'] ?? ''))
							."\n".$msgLine;
						$blocks[] = '<strong>'.htmlspecialchars(\T::Notify_Edit_MaxFetchSectionLastMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong>'
							.'<pre class="mb-0 mt-1 small">'.htmlspecialchars($prePlain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre>';
					}
					if ($chats) {
						$rows = '';
						foreach ($chats as $c) {
							if (!is_array($c)) {
								continue;
							}
							$cid = htmlspecialchars((string) ($c['chat_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
							$ctype = htmlspecialchars((string) ($c['type'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
							$ctitle = htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
							$rows .= '<tr><td class="text-nowrap">'.$cid.'</td><td>'.$ctype.'</td><td>'.$ctitle.'</td></tr>';
						}
						$blocks[] = '<strong class="d-block mt-3">'.htmlspecialchars(\T::Notify_Edit_MaxFetchSectionChats(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</strong>'
							.'<div class="table-responsive mt-1"><table class="table table-sm table-bordered mb-0">'
							.'<thead><tr>'
							.'<th>'.htmlspecialchars(\T::Notify_Edit_MaxFetchColChatId(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</th>'
							.'<th>'.htmlspecialchars(\T::Notify_Edit_MaxFetchColType(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</th>'
							.'<th>'.htmlspecialchars(\T::Notify_Edit_MaxFetchColTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</th>'
							.'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
					}
					$modalInner = implode('', $blocks);
					?>
				<div class="modal fade" id="max-fetch-last-modal" tabindex="-1" aria-labelledby="max-fetch-last-modal-label" aria-hidden="true">
					<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
						<div class="modal-content">
							<div class="modal-header">
								<h5 class="modal-title" id="max-fetch-last-modal-label"><?=\T::Notify_Edit_MaxFetchModalTitle();?></h5>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<div class="modal-body">
								<?=$modalInner;?>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?=\T::Notify_Edit_MaxFetchModalClose();?></button>
							</div>
						</div>
					</div>
				</div>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	private function print_javascript() {
		?>
		<script nonce="<?=\CSP::nonceRandom();?>">
			function notify_type_select() {
				const selected_type = $('#type').val();
				console.log(selected_type);
				$('.type-dependence').each(function(){
					$(this).hide();
				});
				$('.type-' + selected_type).each(function(){
					$(this).show();
				});
			}
			$(document).ready(function(){
				notify_type_select();
				$('#type').change(function(){
					notify_type_select();
				});

				$('form').on('submit', function(event) {
					$('form').addClass("was-validated");
				});
				<?php if ($this->maxFetchParsed !== null) { ?>
				var _m = document.getElementById('max-fetch-last-modal');
				if (_m && window.bootstrap && bootstrap.Modal) {
					bootstrap.Modal.getOrCreateInstance(_m).show();
				}
				<?php } ?>
			});
		</script>
		<?php
	}
}