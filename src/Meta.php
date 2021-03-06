<?php
/*  Mimir: mahjong games storage
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

class Meta
{
    /**
     * @var string
     */
    protected $_authToken;
    /**
     * @var integer
     */
    protected $_requestedVersionMajor;
    /**
     * @var integer
     */
    protected $_requestedVersionMinor;

    public function __construct($input = null)
    {
        if (empty($input)) {
            $input = $_SERVER;
        }

        $this->_fillFrom($input);
    }

    protected function _fillFrom($input)
    {
        $this->_authToken = (empty($input['HTTP_X_AUTH_TOKEN']) ? '' : $input['HTTP_X_AUTH_TOKEN']);
        list($this->_requestedVersionMajor, $this->_requestedVersionMinor) = explode('.', (
            empty($input['HTTP_X_API_VERSION']) ? '1.0' : $input['HTTP_X_API_VERSION']
        ));

        $this->_requestedVersionMinor = $this->_requestedVersionMinor ? intval($this->_requestedVersionMinor) : 0;
        $this->_requestedVersionMajor = $this->_requestedVersionMajor ? intval($this->_requestedVersionMajor) : 1;
    }

    public function getAuthToken()
    {
        return $this->_authToken;
    }

    public function getRequestedVersion()
    {
        return [
            $this->_requestedVersionMajor,
            $this->_requestedVersionMinor
        ];
    }

    public function sendVersionHeader($major, $minor)
    {
        header('X-Api-Version: ' . intval($major) . '.' . intval($minor));
    }

    public function isGlobalWatcher()
    {
        return $this->_authToken === '0000000000';
    }
}
