<?php
/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\Controller;

use \OCP\IRequest;
use \OCP\AppFramework\Http\RedirectResponse;
use \OCP\AppFramework\Controller;
use \OCP\IConfig;
use \OC\User\Session;

use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\UserService;


/**
 * Class AuthenticationController
 *
 * @package OCA\UserCAS\Controller
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class AuthenticationController extends Controller
{

    /**
     * @var string $appName
     */
    protected $appName;

    /**
     * @var \OCP\IConfig $config
     */
    private $config;

    /**
     * @var \OCA\UserCAS\Service\UserService $userService
     */
    private $userService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    private $appService;

    /**
     * @var Session $userSession
     */
    private $userSession;


    /**
     * AuthenticationController constructor.
     * @param $appName
     * @param IRequest $request
     * @param IConfig $config
     * @param UserService $userService
     * @param AppService $appService
     * @param Session $userSession
     */
    public function __construct($appName, IRequest $request, IConfig $config, UserService $userService, AppService $appService, Session $userSession)
    {
        $this->appName = $appName;
        $this->config = $config;
        $this->userService = $userService;
        $this->appService = $appService;
        $this->userSession = $userSession;
        parent::__construct($appName, $request);
    }

    /**
     * Login method.
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     * @PublicPage
     *
     * @return \OCP\AppFramework\Http\RedirectResponse
     */
    public function casLogin()
    {

        $redirectUrl = $this->request->getParam("redirect_url", '');

        if (is_string($redirectUrl) && strlen($redirectUrl) > 0) {

            $location = $this->appService->getAbsoluteURL($redirectUrl);
        } else {

            $location = $this->appService->getAbsoluteURL("/");
        }

        if (!$this->userService->isLoggedIn()) {

            if (!$this->appService->isCasInitialized()) $this->appService->init();

            try {
                
                /**
                 *   20170820
                 *   while infinit authentifation loop 
                 *   and no possibillyty to set proxies via form
                 *   we need this workarround for cassava server
                 *   
                 *   set CAS_Client::_clearTicketsFromUrl to false 
                 */
                \phpCAS::setNoClearTicketsFromUrl();
                
                if (\phpCAS::isAuthenticated()) {

                    $userName = \phpCAS::getUser();

                    \OCP\Util::writeLog('cas', "phpCAS user " . $userName . " has been authenticated.", \OCP\Util::DEBUG);

                    $isLoggedIn = $this->userService->login($this->request, $userName, '');

                    //$isLoggedIn = TRUE;
                    if ($isLoggedIn) {

                        \OCP\Util::writeLog('cas', "phpCAS user has been authenticated against owncloud.", \OCP\Util::DEBUG);

                        return new RedirectResponse($location);
                    } else { # Not authenticated against owncloud

                        \OCP\Util::writeLog('cas', "phpCAS user has not been authenticated against owncloud.", \OCP\Util::ERROR);

                        return new RedirectResponse($this->appService->linkToRoute('core.login.showLoginForm'));
                    }
                } else { # Not authenticated against CAS

                    \OCP\Util::writeLog('cas', "phpCAS user is not authenticated, redirect to CAS server.", \OCP\Util::DEBUG);

                    \phpCAS::forceAuthentication();
                }
            } catch (\CAS_Exception $e) {

                \OCP\Util::writeLog('cas', "phpCAS has thrown an exception with code: " . $e->getCode() . " and message: " . $e->getMessage() . ".", \OCP\Util::ERROR);

                return new RedirectResponse($this->appService->linkToRoute('core.login.showLoginForm'));
            }
        } else {

            \OCP\Util::writeLog('cas', "phpCAS user is already authenticated against owncloud.", \OCP\Util::DEBUG);

            return new RedirectResponse($location);
        }
    }
}
