<?php
/*  Riichi mahjong API game server
 *  Copyright (C) 2016  heilage and others
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

require __DIR__ . '/Config.php';
require __DIR__ . '/Db.php';

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
        $this->_syslog = new Logger('system');
        $this->_syslog->pushHandler(new ErrorLogHandler());
    }

    public function __call($name, $arguments)
    {
        $this->_syslog->info('Method called! ' . $name);
        return 'test data!';
    }
}
