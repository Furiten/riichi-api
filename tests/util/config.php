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

return [
    'db' => [
        'connection_string' => 'sqlite:' . __DIR__ . '/../data/db.sqlite',
        'credentials' => [
            'username' => '',
            'password' => ''
        ]
    ],
    'admin'     => [
        'god_token' => '198vdsh904hfbnkjv98whb2iusvd98b29bsdv98svbr9wghj',
        'debug_token' => '2-839489203hf2893'
    ],
    'routes'    => require __DIR__ . '/../../config/routes.php',
    'verbose'   => true,
    'verboseLog' => __DIR__ . '/../data/verbose.log',
    'trackerUrl' => null,
    'api' => [
        'version_major' => 1,
        'version_minor' => 0
    ]
];
