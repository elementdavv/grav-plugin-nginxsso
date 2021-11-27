<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Plugin;
use Grav\Framework\Session\SessionInterface;

class NginxSso extends Plugin {

    Protected SessionInterface $session;

    public static function getSubscribedEvents() {

        return [
            'onPluginsInitialized'      => ['onPluginsInitialized', 0],
            'onTwigTemplatePaths'       => ['onTwigTemplatePaths', 0],
        ];

    }

    public function onPluginsInitialized() {

        if (!$this->config->get('plugins.nginxsso.enabled')) {
            return;
        }

        // ensure session be enabled.
        if (!$this->config->get('system.session.enabled')) {
            throw new \RuntimeException('The Nginx Sso plugin requires "system.session" to be enabled');
        }

        $this->session = $this->grav['session'];

        // if user passed nginxsso, login the user in grav
        if (isset($_SERVER['HTTP_REMOTE_USER'])) {
            $remote_user = $_SERVER['HTTP_REMOTE_USER'];
            if (!$this->isLogined($remote_user)) {
                $this->ssologin($remote_user);
            }
        }
        // else, do nothing

    }

    protected function isLogined($remote_user) {

        $user = $this->session->user;
        // var_dump($user);
        if (!$user || !$user->exists()) return false;
        if ($remote_user == $user->email) return true;
        $this->ssologout();
        return false;

    }

    protected function ssologout() {

        $user = $this->session->user;
        $user->authenticated = false;
        $user->authorized = false;
        $this->session->invalidate()->start();

    }

    protected function ssologin($username) {

	    $data = [];
	    $data['username'] = $username;
	    $data['email'] = $username;
	    $data['level'] = 'Newbie';
	    $data['state'] = 'enabled';

	    $data_object = (object) $data;
	    $user = $this->ssoregister((array)$data_object);

	    if (is_callable([$user, 'refresh'])) {
		    $user->refresh();
	    }
	    if (method_exists($this->session, 'regenerateId')) {
		    $this->session->regenerateId();
	    }

	    $user->def('language', 'en');
        $user->authenticated = true;
        $user->authorized = true;
	    $this->session->user = $user;

    }

    protected function ssoregister(array $data) {

	    $groups = (array) $this->grav['config']->get('plugins.login.user_registration.groups', []);
	    if (\count($groups) > 0) {
		    $data['groups'] = $groups;
	    }

	    $access = (array) $this->grav['config']->get('plugins.login.user_registration.access.site', []);
	    if (\count($access) > 0) {
		    $data['access']['site'] = $access;
	    }

	    $users = $this->grav['accounts'];
	    $user = $users->load($data['username']);
	    if ($user->exists()) {
		    return $user;
	    }

	    $user->update($data, []);
	    if (isset($data['groups'])) {
		    $user->groups = $data['groups'];
	    }
	    if (isset($data['access'])) {
		    $user->access = $data['access'];
	    }
	    $user->save();

	    return $user;

    }

    /**
     * [onTwigTemplatePaths] Add twig paths to plugin templates.
     */
    public function onTwigTemplatePaths() {

        $twig = $this->grav['twig'];
        $twig->twig_paths[] = __DIR__ . '/templates';

    }

}
