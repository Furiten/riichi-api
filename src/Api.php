<?php
/*  Riichi mahjong API game server
 *  Copyright (C) 2016  o.klimenko aka ctizen
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace Riichi;

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;

class Api
{
    protected $_db;
    protected $_syslog;

    public function __construct()
    {
        $this->_config = new Config(__DIR__ . '/../config/index.php');
        $this->_db = new Db($this->_config);
        $this->_syslog = new Logger('RiichiApi');
        $this->_syslog->pushHandler(new ErrorLogHandler());
    }

    public function getTimezone()
    {
        return 'Asia/Novosibirsk'; // TODO
    }

    public function registerImplAutoloading()
    {
        spl_autoload_register(function ($class) {
            $class = ucfirst(str_replace([__NAMESPACE__ . '\\', 'Controller'], '', $class));
            $classFile = __DIR__ . '/controllers/' . $class . '.php';
            if (is_file($classFile) && !class_exists($class)) {
                include_once $classFile;
            } else {
                $this->_syslog->error('Couldn\'t find module ' . $classFile);
            }
        });
    }

    public function getMethods()
    {
        $runtimeCache = [];
        $routes = $this->_config->getValue('routes');
        return array_map(function ($route) use (&$runtimeCache) {
            // We should instantiate every controller here to enable proper reflection inspection in rpc-server
            $ret = [
                'instance' => null,
                'method' => $route[1],
                'className' => $route[0]
            ];

            if (!empty($runtimeCache[$route[0]])) {
                $ret['instance'] = $runtimeCache[$route[0]];
            } else {
                class_exists($route[0]); // this will ensure class existence
                $className = __NAMESPACE__ . '\\' . $route[0];
                $ret['instance'] = $runtimeCache[$route[0]] = new $className($this->_db, $this->_syslog);
            }

            return $ret;
        }, $routes);
    }

    public function log($message)
    {
        $this->_syslog->info($message);
    }
}
