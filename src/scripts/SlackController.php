<?php

require_once 'Utils.php';
require_once 'SlackModel.php';

class SlackController {

    private $model;
    private $workflows;
    private $results;
    
    public function __construct () {
        $this->workflows = Utils::getWorkflows();
        $this->model = new SlackModel();
        $this->results = [];
    }

    public function getChannelsAction ($search, $message = null) {
        $results = [];

        $auth = $this->model->getAuth();

        $channels = $this->model->getChannels(true);
        foreach ($channels as $channel) {
            $results[] = [
                'id' => $channel->id,
                'title' => '#'.$channel->name,
                'description' => 'Channel - ' . $channel->num_members . ' members - ' . ($channel->is_member ? 'Already a member' : 'Not a member'),
                'data' => Utils::extend($channel, [ 'type' => 'channel', 'auth' => $auth, 'message' => $message ])
            ];
        }

        $groups = $this->model->getGroups(true);
        foreach ($groups as $group) {
            $results[] = [
                'id' => $group->id,
                'title' => '#'.$group->name,
                'description' => 'Group - ' . count($group->members) . ' members',
                'data' => Utils::extend($group, [ 'type' => 'group', 'auth' => $auth, 'message' => $message ])
            ];
        }

        $users = $this->model->getUsers(true);
        foreach ($users as $user) {
            $icon = $this->model->getProfileIcon($user);
            $results[] = [
                'id' => $user->id,
                'title' => '@'.$user->name,
                'description' => 'User - ' . $user->profile->real_name,
                'icon' => $icon,
                'data' => Utils::extend($user, [ 'type' => 'user', 'auth' => $auth, 'message' => $message ])
            ];
        }

        $this->addResults($results, $search);
    }

    public function getConfigActionsAction ($search, $param) {
        $results = [
            [
                'title' => '--token',
                'data' => [ 'token' => $param ]
            ]
        ];
        $this->addResults($results, $search);
    }

    public function openChannelAction ($data) {

        $id = $data->id;

        // Get the IM id if a user
        if ($data->type === 'user') {
            $id = $this->model->getImIdByUserId($data->id);
        }

        $url = 'slack://channel?id='.$id.'&team='.$data->auth->team_id;
        Utils::openUrl($url);
    }

    public function sendMessageAction ($data) {

        $id = $data->id;

        // Get the IM id if a user
        if ($data->type === 'user') {
            $id = $this->model->getImIdByUserId($data->id);
        }

        $this->model->postMessage($id, $data->message);
    }

    private function addResults ($array, $search) {
        $found = [];
        foreach ($array as $element) {
            $title = strtolower(trim($element['title']));

            if ($title === $search) {
                if (!isset($found[$title])) {
                    $found[$title] = true;
                    $this->results[0][] = $element;
                }
            }
            else if (strpos($title, $search) === 0) {
                if (!isset($found[$title])) {
                    $found[$title] = true;
                    $this->results[1][] = $element;
                }
            }
            else if (strpos($title, $search) > 0) {
                if (!isset($found[$title])) {
                    $found[$title] = true;
                    $element['__searchIndex'] = strpos($title, $search);
                    $this->results[2][] = $element;
                }
            }
        }

        if (isset($this->results[2])) {
            usort($this->results[2], function ($a, $b) {
                if ($a['__searchIndex'] === $b['__searchIndex']) {
                    $al = strlen($a['title']);
                    $bl = strlen($b['title']);
                    if ($al === $bl) {
                        return 0;
                    }
                    return ($al < $bl) ? -1 : 1;
                }
                return ($a['__searchIndex'] < $b['__searchIndex']) ? -1 : 1;
            });
        }

        ksort($this->results);

        $this->render();
    }

    private function render () {
        foreach ($this->results as $level => $results) {
            foreach ($results as $result) {
                $icon = isset($result['icon']) ? $result['icon'] : Utils::$icon;
                $this->workflows->result($result['id'], json_encode($result['data']), $result['title'], $result['description'], $icon);
            }
        }
        echo $this->workflows->toxml();
    }
}