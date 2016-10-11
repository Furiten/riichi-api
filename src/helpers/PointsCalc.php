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

class PointsCalc
{
    public static function ron(
        Ruleset $rules,
        $isDealer,
        $currentScores,
        $winnerId,
        $loserId,
        $han,
        $fu,
        $riichiIds,
        $honba,
        $riichiBetsCount
    ) {
        $pointsDiff = self::_calcPoints($rules, $han, $fu, false, $isDealer);

        if (empty($winnerId) || empty($loserId)) {
            throw new InvalidParametersException('Ron must have winner and loser');
        }

        $currentScores[$winnerId] += $pointsDiff['winner'];
        $currentScores[$loserId] += $pointsDiff['loser'];

        if (empty($riichiIds)) {
            $riichiIds = [];
        }

        foreach ($riichiIds as $playerId) {
            $currentScores[$playerId] -= 1000;
        }

        $currentScores[$winnerId] += 1000 * count($riichiIds);
        $currentScores[$winnerId] += 1000 * $riichiBetsCount;
        $currentScores[$winnerId] += 300 * $honba;

        $currentScores[$loserId] -= 300 * $honba;

        return $currentScores;
    }

    public static function tsumo(
        Ruleset $rules,
        $currentDealer,
        $currentScores,
        $winnerId,
        $han,
        $fu,
        $riichiIds,
        $honba,
        $riichiBetsCount
    ) {
        $pointsDiff = self::_calcPoints($rules, $han, $fu, true, $currentDealer == $winnerId);

        if (empty($winnerId)) {
            throw new InvalidParametersException('Tsumo must have winner');
        }

        $currentScores[$winnerId] += $pointsDiff['winner'];

        if ($currentDealer == $winnerId) { // dealer tsumo
            foreach ($currentScores as $playerId => $value) {
                if ($playerId == $winnerId) {
                    continue;
                }
                $currentScores[$playerId] += $pointsDiff['dealer'];
            }
        } else {
            foreach ($currentScores as $playerId => $value) {
                if ($playerId == $winnerId) {
                    continue;
                }
                if ($playerId == $currentDealer) {
                    $currentScores[$playerId] += $pointsDiff['dealer'];
                } else {
                    $currentScores[$playerId] += $pointsDiff['player'];
                }
            }
        }

        if (empty($riichiIds)) {
            $riichiIds = [];
        }

        foreach ($riichiIds as $playerId) {
            $currentScores[$playerId] -= 1000;
        }

        $currentScores[$winnerId] += 1000 * count($riichiIds);
        $currentScores[$winnerId] += 1000 * $riichiBetsCount;
        $currentScores[$winnerId] += 300 * $honba;

        foreach ($currentScores as $playerId => $value) {
            if ($playerId == $winnerId) {
                continue;
            }
            $currentScores[$playerId] -= 100 * $honba;
        }

        return $currentScores;
    }

    public static function draw(
        $currentScores,
        $tempaiIds,
        $riichiIds
    ) {
        if (empty($riichiIds)) {
            $riichiIds = [];
        }

        foreach ($riichiIds as $playerId) {
            $currentScores[$playerId] -= 1000;
        }

        if (count($tempaiIds) === 0 || count($tempaiIds) === 4) {
            return $currentScores;
        }

        if (count($tempaiIds) === 1) {
            foreach ($currentScores as $playerId => $value) {
                if ($playerId == $tempaiIds[0]) {
                    $currentScores[$playerId] += 3000;
                } else {
                    $currentScores[$playerId] -= 1000;
                }
            }
            return $currentScores;
        }

        if (count($tempaiIds) === 2) {
            foreach ($currentScores as $playerId => $value) {
                if (in_array($playerId, $tempaiIds)) {
                    $currentScores[$playerId] += 1500;
                } else {
                    $currentScores[$playerId] -= 1500;
                }
            }
            return $currentScores;
        }

        if (count($tempaiIds) === 3) {
            foreach ($currentScores as $playerId => $value) {
                if (in_array($playerId, $tempaiIds)) {
                    $currentScores[$playerId] += 1000;
                } else {
                    $currentScores[$playerId] -= 3000;
                }
            }
            return $currentScores;
        }

        throw new InvalidParametersException('More than 4 players tempai? o_0');
    }

    public static function abort(
        $currentScores,
        $riichiIds
    ) {
        if (empty($riichiIds)) {
            $riichiIds = [];
        }

        foreach ($riichiIds as $playerId) {
            $currentScores[$playerId] -= 1000;
        }

        return $currentScores;
    }

    public static function chombo(
        Ruleset $rules,
        $currentDealer,
        $loserId,
        $currentScores
    ) {
        if (empty($loserId)) {
            throw new InvalidParametersException('Chombo must have loser');
        }

        if ($rules->extraChomboPayments()) {
            if ($currentDealer == $loserId) {
                foreach ($currentScores as $playerId => $value) {
                    if ($playerId == $loserId) {
                        $currentScores[$playerId] -= 12000;
                    } else {
                        $currentScores[$playerId] += 4000;
                    }
                }
            } else {
                foreach ($currentScores as $playerId => $value) {
                    if ($playerId == $loserId) {
                        $currentScores[$playerId] -= 8000;
                    } else if ($playerId == $currentDealer) {
                        $currentScores[$playerId] += 4000;
                    } else {
                        $currentScores[$playerId] += 2000;
                    }
                }
            }
        }

        return $currentScores;
    }

    protected static function _calcPoints(Ruleset $rules, $han, $fu, $tsumo, $dealer)
    {
        if ($han < 5) {
            $basePoints = $fu * pow(2, 2 + $han);
            $rounded = ceil($basePoints / 100.) * 100;
            $doubleRounded = ceil(2 * $basePoints / 100.) * 100;
            $timesFourRounded = ceil(4 * $basePoints / 100.) * 100;
            $timesSixRounded = ceil(6 * $basePoints / 100.) * 100;

            $isKiriage = $rules->withKiriageMangan() && (
                ($han == 4 && $fu == 30) ||
                ($han == 3 && $fu == 60)
            );

            // mangan
            if ($basePoints >= 2000 || $isKiriage) {
                $rounded = 2000;
                $doubleRounded = $rounded * 2;
                $timesFourRounded = $doubleRounded * 2;
                $timesSixRounded = $doubleRounded * 3;
            }
        } else { // limits
            // yakuman
            if ($rules->withKazoe() && $han >= 13) {
                $rounded = 8000;
            } // sanbaiman
            else if ($han >= 11) {
                $rounded = 6000;
            } // baiman
            else if ($han >= 8) {
                $rounded = 4000;
            } // haneman
            else if ($han >= 6) {
                $rounded = 3000;
            } else {
                $rounded = 2000;
            }
            $doubleRounded = $rounded * 2;
            $timesFourRounded = $doubleRounded * 2;
            $timesSixRounded = $doubleRounded * 3;
        }

        if ($tsumo) {
            return [
                'winner' => $dealer
                    ? (int)(3 * $doubleRounded)
                    : (int)($doubleRounded + (2 * $rounded)),
                'dealer' => (int)-$doubleRounded,
                'player' => (int)-$rounded
            ];
        } else {
            return [
                'winner' => $dealer ? (int)$timesSixRounded : (int)$timesFourRounded,
                'loser' => $dealer ? (int)-$timesSixRounded : (int)-$timesFourRounded
            ];
        }
    }
}