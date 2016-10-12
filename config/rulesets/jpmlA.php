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
require_once __DIR__ . '/../../src/Ruleset.php';

class RulesetJpmlA extends Ruleset
{
    public static $_name = 'jpmlA';
    protected static $_ruleset = [
        'tenboDivider'          => 100,
        'ratingDivider'         => 10,
        'startRating'           => 1500,
        'oka'                   => 0,
        'startPoints'           => 300,
        'riichiGoesToWinner'    => false,
        'extraChomboPayments'   => true,
        'chomboPenalty'         => 200,
        'withAtamahane'         => true,
        'withAbortives'         => true,
        'withKuitan'            => true,
        'withKazoe'             => true,
        'withButtobi'           => true,
        'withMultiYakumans'     => false,
        'withOpenRiichi'        => false,
        'withNagashiMangan'     => false,
        'withKiriageMangan'     => false,
        'tonpuusen'             => false,
        'withLeadingDealerGameOver' => true
    ];

    /**
     * JPML A uses complex uma bonus
     *
     * @param array $scores
     * @return array
     */
    public function uma($scores)
    {
        rsort($scores);
        $minusedPlayers = array_reduce($scores, function($el) {
            return $el < $this->startPoints() ? 1 : 0;
        }, 0);

        switch($minusedPlayers) {
            case 4:
            case 2:
            case 0:
                return [1 => 8, 4, -4, -8];
            case 3:
                return [1 => 12, -1, -3, -8];
            case 1:
                return [1 => 8, 3, 1, -12];
        }
    }
}