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

require_once __DIR__ . '/../models/InteractiveSession.php';
require_once __DIR__ . '/../models/TextmodeSession.php';
require_once __DIR__ . '/../models/OnlineSession.php';
require_once __DIR__ . '/../Controller.php';

class GamesController extends Controller
{
    // INTERACTIVE MODE

    /**
     * Start new interactive game and return its hash
     *
     * @param int $eventId Event this session belongs to
     * @param array $players Player id list
     * @throws InvalidUserException
     * @throws DatabaseException
     * @return string Hashcode of started game
     */
    public function start($eventId, $players)
    {
        $this->_log->addInfo('Starting game with players id# ' . implode(',', $players));
        $gameHash = (new InteractiveSessionModel($this->_db, $this->_config))->startGame($eventId, $players);
        $this->_log->addInfo('Successfully started game with players id# ' . implode(',', $players));
        return $gameHash;
    }

    /**
     * Start new interactive game and return its hash
     *
     * @param array $players Player id list
     * @throws InvalidUserException
     * @throws DatabaseException
     * @throws InvalidParametersException
     * @return string Hashcode of started game
     */
    public function startFromToken($players)
    {
        $this->_log->addInfo('Starting new game (by token)');
        $data = (new EventModel($this->_db, $this->_config))->dataFromToken();
        if (empty($data)) {
            throw new InvalidParametersException('Invalid user token', 401);
        }
        return $this->start($data->getEventId(), $players);
    }

    /**
     * Drop last round from selected game
     * For interactive mode (tournaments), and only for administrative purposes
     *
     * @param string $gameHashcode
     * @return boolean Success?
     */
    public function dropLastRound($gameHashcode)
    {
        $this->_log->addInfo('Dropping last round from session #' . $gameHashcode);
        $success = (new InteractiveSessionModel($this->_db, $this->_config))->dropLastRound($gameHashcode);
        $this->_log->addInfo('Successfully dropped last round from session #' . $gameHashcode);
        return $success;
    }

    /**
     * Explicitly force end of interactive game
     *
     * @param $gameHashcode string Hashcode of game
     * @throws DatabaseException
     * @throws BadActionException
     * @return bool Success?
     */
    public function end($gameHashcode)
    {
        $this->_log->addInfo('Finishing game # ' . $gameHashcode);
        $result = (new InteractiveSessionModel($this->_db, $this->_config))->endGame($gameHashcode);
        $this->_log->addInfo(($result ? 'Successfully finished' : 'Failed to finish') . ' game # ' . $gameHashcode);
        return $result;
    }

    /**
     * Add new round to interactive game
     *
     * @param string $gameHashcode Hashcode of game
     * @param array $roundData Structure of round data
     * @param boolean $dry Dry run (without saving to db)
     * @throws DatabaseException
     * @throws BadActionException
     * @return bool|array Success|Results of dry run
     */
    public function addRound($gameHashcode, $roundData, $dry = false)
    {
        $this->_log->addInfo('Adding new round to game # ' . $gameHashcode);
        $result = (new InteractiveSessionModel($this->_db, $this->_config))->addRound($gameHashcode, $roundData, $dry);
        $this->_log->addInfo(($result ? 'Successfully added' : 'Failed to add') . ' new round to game # ' . $gameHashcode);
        return $result;
    }

    /**
     * Add penalty in interactive game
     *
     * @param integer $eventId Hashcode of game
     * @param integer $playerId Id of penalized player
     * @param integer $amount Penalty amount
     * @param string $reason Panelty reason
     * @throws DatabaseException
     * @throws BadActionException
     * @return bool Success?
     */
    public function addPenalty($eventId, $playerId, $amount, $reason)
    {
        $this->_log->addInfo('Adding penalty for player #' . $playerId. ' to event # ' . $eventId);
        $result = (new InteractiveSessionModel($this->_db, $this->_config))
            ->addPenalty($eventId, $playerId, $amount, $reason);
        $this->_log->addInfo('Successfully added penalty for player #' . $playerId. ' to event # ' . $eventId);
        return $result;
    }

    /**
     * Get session overview
     * [
     *      id => sessionId,
     *      players => [ ..[
     *          id => playerId,
     *          display_name,
     *          ident
     *      ].. ],
     *      state => [
     *          dealer => playerId,
     *          round => int,
     *          riichi => [ ..playerId.. ],
     *          honba => int,
     *          scores => [ ..int.. ],
     *          finished => boolean
     *      ]
     * ]
     *
     * @param string $sessionHashcode
     * @throws EntityNotFoundException
     * @throws InvalidParametersException
     * @return array
     */
    public function getSessionOverview($sessionHashcode)
    {
        $this->_log->addInfo('Getting session overview for game # ' . $sessionHashcode);
        $session = SessionPrimitive::findByRepresentationalHash($this->_db, [$sessionHashcode]);
        if (empty($session)) {
            throw new InvalidParametersException("Couldn't find session in DB", 404);
        }

        $result = [
            'id'    => $session[0]->getId(),
            'table_index' => $session[0]->getTableIndex(),
            'players' => array_map(function (PlayerPrimitive $player) {
                return [
                    'id' => $player->getId(),
                    'display_name' => $player->getDisplayName(),
                    'ident' => $player->getIdent()
                ];
            }, $session[0]->getPlayers()),

            'state' => [
                'dealer'    => $session[0]->getCurrentState()->getCurrentDealer(),
                'round'     => $session[0]->getCurrentState()->getRound(),
                'riichi'    => $session[0]->getCurrentState()->getRiichiBets(),
                'honba'     => $session[0]->getCurrentState()->getHonba(),
                'scores'    => $session[0]->getCurrentState()->getScores(),
                'finished'  => $session[0]->getCurrentState()->isFinished()
            ]
        ];

        $this->_log->addInfo('Successfully got session overview for game # ' . $sessionHashcode);
        return $result;
    }

    // TEXT LOG MODE

    /**
     * Add textual log for whole game
     *
     * @param int $eventId
     * @param string $text
     * @return bool
     * @throws InvalidParametersException
     * @throws ParseException
     */
    public function addTextLog($eventId, $text)
    {
        $this->_log->addInfo('Saving new game for event id# ' . $eventId);
        $success = (new TextmodeSessionModel($this->_db, $this->_config))->addGame($eventId, $text);
        $this->_log->addInfo('Successfully saved game for event id# ' . $eventId);
        return $success;
    }

    // ONLINE REPLAY MODE

    /**
     * Add online replay
     *
     * @param int $eventId
     * @param string $link
     * @return bool
     * @throws InvalidParametersException
     * @throws ParseException
     */
    public function addOnlineReplay($eventId, $link)
    {
        $this->_log->addInfo('Saving new online game for event id# ' . $eventId);
        $success = (new OnlineSessionModel($this->_db, $this->_config))->addGame($eventId, $link);
        $this->_log->addInfo('Successfully saved online game for event id# ' . $eventId);
        return $success;
    }
}
