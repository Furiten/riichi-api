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

use \Idiorm\ORM;

require_once __DIR__ . '/../exceptions/EntityNotFound.php';
require_once __DIR__ . '/../exceptions/InvalidParameters.php';
require_once __DIR__ . '/../Primitive.php';
require_once __DIR__ . '/../helpers/SessionState.php';

/**
 * Class SessionResultsPrimitive
 *
 * Low-level model with basic CRUD operations and relations
 * @package Riichi
 */
class SessionResultsPrimitive extends Primitive
{
    protected static $_table = 'session_results';

    protected static $_fieldsMapping = [
        'id'                    => '_id',
        'event_id'              => '_eventId',
        'session_id'            => '_sessionId',
        'player_id'             => '_playerId',
        'score'                 => '_score',
        'result_score'          => '_resultScore',
        'place'                 => '_place'
    ];

    protected function _getFieldsTransforms()
    {
        return [
            '_eventId'      => $this->_integerTransform(),
            '_sessionId'    => $this->_integerTransform(),
            '_playerId'     => $this->_integerTransform(),
            '_id'           => $this->_nullableIntegerTransform()
        ];
    }

    /**
     * Local id
     * @var int
     */
    protected $_id;

    /**
     * @var int
     */
    protected $_eventId;
    /**
     *
     * @var EventPrimitive
     */
    protected $_event;

    /**
     * @var int
     */
    protected $_sessionId;
    /**
     * @var SessionPrimitive
     */
    protected $_session;

    /**
     * @var int
     */
    protected $_playerId;
    /**
     * @var PlayerPrimitive
     */
    protected $_player;

    /**
     * @var int
     */
    protected $_score;

    /**
     * @var float
     */
    protected $_resultScore;

    /**
     * @var int
     */
    protected $_place;

    /**
     * Find sessions by local ids (primary key)
     *
     * @param IDb $db
     * @param int[] $ids
     * @throws \Exception
     * @return SessionResultsPrimitive[]
     */
    public static function findById(IDb $db, $ids)
    {
        return self::_findBy($db, 'id', $ids);
    }

    /**
     * Find sessions by event id (foreign key search)
     *
     * @param IDb $db
     * @param string[] $eventIds
     * @throws \Exception
     * @return SessionResultsPrimitive[]
     */
    public static function findByEventId(IDb $db, $eventIds)
    {
        return self::_findBy($db, 'event_id', $eventIds);
    }

    /**
     * Find sessions by session id (foreign key search)
     *
     * @param IDb $db
     * @param string[] $sessionIds
     * @throws \Exception
     * @return SessionResultsPrimitive[]
     */
    public static function findBySessionId(IDb $db, $sessionIds)
    {
        return self::_findBy($db, 'session_id', $sessionIds);
    }

    /**
     * Find sessions by player id (foreign key search)
     *
     * @param IDb $db
     * @param string[] $playerIds
     * @throws \Exception
     * @return SessionResultsPrimitive[]
     */
    public static function findByPlayerId(IDb $db, $playerIds)
    {
        return self::_findBy($db, 'player_id', $playerIds);
    }

    protected function _create()
    {
        $sessionReuslts = $this->_db->table(self::$_table)->create();
        $success = $this->_save($sessionReuslts);
        if ($success) {
            $this->_id = $this->_db->lastInsertId();
        }

        return $success;
    }

    /**
     * @deprecated
     * @param EventPrimitive $event
     * @throws InvalidParametersException
     */
    public function _setEvent(EventPrimitive $event)
    {
        throw new InvalidParametersException('Event should not be set directly to round. Set session instead!');
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\EventPrimitive
     */
    public function getEvent()
    {
        if (!$this->_event) {
            $this->_event = $this->getSession()->getEvent();
            $this->_eventId = $this->_event->getId();
        }
        return $this->_event;
    }

    /**
     * @return int
     */
    public function getEventId()
    {
        return $this->_eventId;
    }

    /**
     * @param \Riichi\SessionPrimitive $session
     * @return SessionResultsPrimitive
     */
    public function setSession(SessionPrimitive $session)
    {
        $this->_session = $session;
        $this->_sessionId = $session->getId();
        $this->_eventId = $session->getEventId();
        return $this;
    }

    /**
     * @return SessionPrimitive
     * @throws EntityNotFoundException
     */
    public function getSession()
    {
        if (!$this->_session) {
            $foundSessions = SessionPrimitive::findById($this->_db, [$this->_sessionId]);
            if (empty($foundSessions)) {
                throw new EntityNotFoundException("Entity SessionPrimitive with id#" . $this->_sessionId . ' not found in DB');
            }
            $this->_session = $foundSessions[0];
        }
        return $this->_session;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param \Riichi\PlayerPrimitive $player
     * @return SessionResultsPrimitive
     */
    public function setPlayer(PlayerPrimitive $player)
    {
        $this->_player = $player;
        $this->_playerId = $player->getId();
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\PlayerPrimitive
     */
    public function getPlayer()
    {
        if (!$this->_player) {
            $foundUsers = PlayerPrimitive::findById($this->_db, [$this->_playerId]);
            if (empty($foundUsers)) {
                throw new EntityNotFoundException("Entity PlayerPrimitive with id#" . $this->_playerId . ' not found in DB');
            }
            $this->_player = $foundUsers[0];
        }
        return $this->_player;
    }

    /**
     * @return int
     */
    public function getPlayerId()
    {
        return $this->_playerId;
    }

    /**
     * @return int
     */
    public function getScore()
    {
        return $this->_score;
    }

    /**
     * @return int
     */
    public function getPlace()
    {
        return $this->_place;
    }

    /**
     * @return float
     */
    public function getResultScore()
    {
        return $this->_resultScore;
    }

    /**
     * @param Ruleset $rules
     * @param SessionState $results
     * @param int[] $playerIds
     * @return SessionResultsPrimitive
     */
    public function calc(Ruleset $rules, SessionState $results, $playerIds)
    {
        for ($i = 0; $i < count($playerIds); $i++) {
            if ($playerIds[$i] == $this->_playerId) {
                $this->_score = $results->getScores()[$i];
                break;
            }
        }

        list($place, $playerBonus) = $this->_calcPlaceAndBonus(
            $this->_score,
            $results->getScores(),
            $playerIds,
            $rules
        );

        $this->_place = $place;

        $this->_resultScore = ($this->_score / (float)$rules->tenboDivider()) + $playerBonus;
        if ($this->_place === 1) {
            $this->_resultScore += $rules->oka();
        }

        return $this;
    }

    /**
     * Calculates place and rank bonus (uma)
     * Should depend on ruleset (in some rules equal score result in equal bonus)
     *
     * @param $score
     * @param $scoreList
     * @param $originalPlayersSequence
     * @param Ruleset $rules
     */
    protected function _calcPlaceAndBonus($score, $scoreList, $originalPlayersSequence, Ruleset $rules)
    {
        // 1) no equal
        // 2) two equals
        // 3) three equals
        // 4) all equal
    }
}