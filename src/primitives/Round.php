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

require_once __DIR__ . '/Player.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Event.php';
require_once __DIR__ . '/../Primitive.php';
require_once __DIR__ . '/../validators/Round.php';

/**
 * Class EventPrimitive
 *
 * Low-level model with basic CRUD operations and relations
 * @package Riichi
 */
class RoundPrimitive extends Primitive
{
    protected static $_table = 'round';

    protected static $_fieldsMapping = [
        'id'            => '_id',
        'session_id'    => '_sessionId',
        'event_id'      => '_eventId',
        'outcome'       => '_outcome',
        'han'           => '_han',
        'fu'            => '_fu',
        'round'         => '_roundIndex',
        'dora'          => '_dora',
        'uradora'       => '_uradora',
        'kandora'       => '_kandora',
        'kanuradora'    => '_kanuradora',
        'multi_ron'     => '_multiRon',
        'riichi'        => '_riichiIds',
        'yaku'          => '_yaku',
        'tempai'        => '_tempaiIds',
        'winner_id'     => '_winnerId',
        'loser_id'      => '_loserId',
        'open_hand'     => '_openHand',
        'last_session_state' => '_lastSessionState'
    ];

    protected function _getFieldsTransforms()
    {
        return [
            '_eventId' => [
                'serialize' => function () {
                    return (int)$this->getSession()->getEventId();
                }
            ],
            '_tempaiIds'  => $this->_csvTransform(),
            '_riichiIds'  => $this->_csvTransform(),
            '_winnerId'   => $this->_integerTransform(true),
            '_loserId'    => $this->_integerTransform(true),
            '_sessionId'  => $this->_integerTransform(),
            '_han'        => $this->_integerTransform(true),
            '_fu'         => $this->_integerTransform(true),
            '_dora'       => $this->_integerTransform(true),
            '_uradora'    => $this->_integerTransform(true),
            '_kandora'    => $this->_integerTransform(true),
            '_kanuradora' => $this->_integerTransform(true),
            '_multiRon'   => $this->_integerTransform(true),
            '_id'         => $this->_integerTransform(true),
            '_openHand'   => $this->_integerTransform(),
            '_lastSessionState' => [
                'serialize' => function () {
                    return $this->getSession()->getCurrentState()->toJson();
                } // don't do explicit deserialize here, as it may be not required in client code
            ]
        ];
    }

    /**
     * @var int
     */
    protected $_id;
    /**
     * @var SessionPrimitive
     */
    protected $_session;
    /**
     * @var int
     */
    protected $_sessionId;
    /**
     * @var EventPrimitive
     */
    protected $_event;
    /**
     * @var int
     */
    protected $_eventId;
    /**
     * ron, tsumo, draw, abortive draw or chombo
     * @var string
     */
    protected $_outcome;
    /**
     * not null only on ron or tsumo
     * @var PlayerPrimitive
     */
    protected $_winner;
    /**
     * not null only on ron or tsumo
     * @var string
     */
    protected $_winnerId;
    /**
     * not null only on ron or chombo
     * @var PlayerPrimitive
     */
    protected $_loser;
    /**
     * not null only on ron or chombo
     * @var string
     */
    protected $_loserId;
    /**
     * @var int
     */
    protected $_han;
    /**
     * @var int
     */
    protected $_fu;
    /**
     * 1-4 means east1-4, 5-8 means south1-4, etc
     * @var int
     */
    protected $_roundIndex;
    /**
     * @var int[]
     */
    protected $_tempaiIds;
    /**
     * @var PlayerPrimitive[]
     */
    protected $_tempaiUsers = null;
    /**
     * comma-separated yaku id list
     * @var string
     */
    protected $_yaku;
    /**
     * @var int
     */
    protected $_dora = 0;
    /**
     * @var int
     */
    protected $_uradora = 0;
    /**
     * @var int
     */
    protected $_kandora = 0;
    /**
     * @var int
     */
    protected $_kanuradora = 0;
    /**
     * @var int[]
     */
    protected $_riichiIds;
    /**
     * @var PlayerPrimitive[]
     */
    protected $_riichiUsers = null;
    /**
     * double or triple ron flag to properly display results of round
     * @var int
     */
    protected $_multiRon;
    /**
     * JSON of last session state - for round rollback functionality
     * @var string
     */
    protected $_lastSessionState;
    /**
     * @var boolean
     */
    protected $_openHand;

    /**
     * Find rounds by local ids (primary key) - should not be used in business code
     * because it makes no sense with multi-rounds.
     *
     * @deprecated
     * @param IDb $db
     * @param int[] $ids
     * @throws \Exception
     * @return RoundPrimitive[]
     */
    public static function findById(IDb $db, $ids)
    {
        return self::_findBy($db, 'id', $ids);
    }

    /**
     * Find rounds and multi-rounds by session (foreign key search)
     *
     * @param IDb $db
     * @param string[] $idList
     * @throws \Exception
     * @return RoundPrimitive[]
     */
    public static function findBySessionIds(IDb $db, $idList)
    {
        /** @var RoundPrimitive[] $rounds */
        $rounds = self::_findBy($db, 'session_id', $idList);
        return self::_mergeMultiRoundsBySession($db, $rounds);
    }

    // Warning: other foreign key search methods should implement multi-rounds logic,
    // or it will ruin all object model.

    /**
     * @param IDb $db
     * @param RoundPrimitive[] $rounds
     * @return array
     */
    protected static function _mergeMultiRoundsBySession(IDb $db, &$rounds)
    {
        require_once __DIR__ . '/MultiRound.php';
        $splitBySession = [];
        foreach ($rounds as $round) {
            if (empty($splitBySession[$round->getSessionId()])) {
                $splitBySession[$round->getSessionId()] = [];
            }
            $splitBySession[$round->getSessionId()] []= $round;
        }

        $splitBySession = array_map(function ($roundsInSession) use ($db) {
            $result = [];
            usort($roundsInSession, function (RoundPrimitive $el1, RoundPrimitive $el2) {
                // sort by id, so consecutive multi-ron ids will
                // be definitely consequent within array
                return $el1->getId() - $el2->getId();
            });

            /** @var RoundPrimitive[] $roundsInSession */
            for ($i = 0; $i < count($roundsInSession); $i++) {
                $round = $roundsInSession[$i];

                if ($round->getOutcome() !== 'multiron') {
                    $result []= $round;
                    continue;
                }

                // double ron
                if ($round->getMultiRon() == 2) {
                    $result []= MultiRoundPrimitive::createFromRounds($db, [
                        $round,
                        $roundsInSession[$i + 1]
                    ]);
                    $i ++;
                    continue;
                }

                // triple ron
                if ($round->getMultiRon() == 3) {
                    $result []= MultiRoundPrimitive::createFromRounds($db, [
                        $round,
                        $roundsInSession[$i + 1],
                        $roundsInSession[$i + 2]
                    ]);
                    $i += 2;
                    continue;
                }
            }

            return $result;
        }, $splitBySession);

        return array_reduce($splitBySession, 'array_merge', []); // flatten and return
    }

    protected function _create()
    {
        $round = $this->_db->table(self::$_table)->create();
        $success = $this->_save($round);
        if ($success) {
            $this->_id = $this->_db->lastInsertId();
        }

        return $success;
    }

    protected function _deident()
    {
        $this->_id = null;
    }

    /**
     * @param IDb $db
     * @param SessionPrimitive $session
     * @param $roundData
     * @return RoundPrimitive|MultiRoundPrimitive
     */
    public static function createFromData(IDb $db, SessionPrimitive $session, $roundData)
    {
        require_once __DIR__ . '/MultiRound.php';
        if ($roundData['outcome'] === 'multiron') {
            return MultiRoundPrimitive::createFromData($db, $session, $roundData);
        }

        RoundsHelper::checkRound($session, $roundData);
        $roundData['session_id'] = $session->getId();
        $roundData['event_id'] = $session->getEventId();
        $roundData['round'] = $session->getCurrentState()->getRound();
        $roundData['id'] = null;

        // Just set it, as we already checked its perfect validity.
        return (new RoundPrimitive($db))->_restore($roundData);
    }

    /**
     * @param int $dora
     * @return RoundPrimitive
     */
    public function setDora($dora)
    {
        $this->_dora = $dora;
        return $this;
    }

    /**
     * @return int
     */
    public function getDora()
    {
        return $this->_dora;
    }

    /**
     * @deprecated
     * @throws InvalidParametersException
     */
    public function _setEvent()
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
     * @param int $fu
     * @return RoundPrimitive
     */
    public function setFu($fu)
    {
        $this->_fu = $fu;
        return $this;
    }

    /**
     * @return int
     */
    public function getFu()
    {
        return $this->_fu;
    }

    /**
     * @param int $han
     * @return RoundPrimitive
     */
    public function setHan($han)
    {
        $this->_han = $han;
        return $this;
    }

    /**
     * @return int
     */
    public function getHan()
    {
        return $this->_han;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * @param int $kandora
     * @return RoundPrimitive
     */
    public function setKandora($kandora)
    {
        $this->_kandora = $kandora;
        return $this;
    }

    /**
     * @return int
     */
    public function getKandora()
    {
        return $this->_kandora;
    }

    /**
     * @param int $kanuradora
     * @return RoundPrimitive
     */
    public function setKanuradora($kanuradora)
    {
        $this->_kanuradora = $kanuradora;
        return $this;
    }

    /**
     * @return int
     */
    public function getKanuradora()
    {
        return $this->_kanuradora;
    }

    /**
     * @param \Riichi\PlayerPrimitive $loser
     * @return RoundPrimitive
     */
    public function setLoser(PlayerPrimitive $loser)
    {
        $this->_loser = $loser;
        $this->_loserId = $loser->getId();
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\PlayerPrimitive
     */
    public function getLoser()
    {
        if (!$this->_loser) {
            $foundUsers = PlayerPrimitive::findById($this->_db, [$this->_loserId]);
            if (empty($foundUsers)) {
                throw new EntityNotFoundException("Entity PlayerPrimitive with id#" . $this->_loserId . ' not found in DB');
            }
            $this->_loser = $foundUsers[0];
        }
        return $this->_loser;
    }

    /**
     * @return string
     */
    public function getLoserId()
    {
        return $this->_loserId;
    }

    /**
     * @param int $multiRon
     * @return RoundPrimitive
     */
    public function setMultiRon($multiRon)
    {
        $this->_multiRon = $multiRon;
        return $this;
    }

    /**
     * @return int
     */
    public function getMultiRon()
    {
        return $this->_multiRon;
    }

    /**
     * @param string $outcome
     * @return RoundPrimitive
     */
    public function setOutcome($outcome)
    {
        $this->_outcome = $outcome;
        return $this;
    }

    /**
     * @return string
     */
    public function getOutcome()
    {
        return $this->_outcome;
    }

    /**
     * @return \int[]
     */
    public function getRiichiIds()
    {
        return $this->_riichiIds;
    }

    /**
     * @param \Riichi\PlayerPrimitive[] $riichiUsers
     * @return RoundPrimitive
     */
    public function setRiichiUsers($riichiUsers)
    {
        $this->_riichiUsers = $riichiUsers;
        $this->_riichiIds = array_map(function (PlayerPrimitive $player) {
            return $player->getId();
        }, $riichiUsers);
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\PlayerPrimitive[]
     */
    public function getRiichiUsers()
    {
        if ($this->_riichiUsers === null) {
            $this->_riichiUsers = PlayerPrimitive::findById(
                $this->_db,
                $this->_riichiIds
            );
            if (empty($this->_riichiUsers) || count($this->_riichiUsers) !== count($this->_riichiIds)) {
                $this->_riichiUsers = null;
                throw new EntityNotFoundException("Not all players were found in DB (among id#" . implode(',', $this->_riichiIds));
            }
        }
        return $this->_riichiUsers;
    }

    /**
     * @param int $roundIndex
     * @return RoundPrimitive
     */
    public function setRoundIndex($roundIndex)
    {
        $this->_roundIndex = $roundIndex;
        return $this;
    }

    /**
     * @return int
     */
    public function getRoundIndex()
    {
        return $this->_roundIndex;
    }

    /**
     * @param \Riichi\SessionPrimitive $session
     * @return RoundPrimitive
     */
    public function setSession(SessionPrimitive $session)
    {
        $this->_session = $session;
        $this->_sessionId = $session->getId();
        $this->_eventId = $session->getEventId();
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\SessionPrimitive
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
    public function getSessionId()
    {
        return $this->_sessionId;
    }

    /**
     * @return \int[]
     */
    public function getTempaiIds()
    {
        if (empty($this->_tempaiIds)) {
            $this->_tempaiIds = [];
        }
        return $this->_tempaiIds;
    }

    /**
     * @param \Riichi\PlayerPrimitive[] $tempaiUsers
     * @return RoundPrimitive
     */
    public function setTempaiUsers($tempaiUsers)
    {
        $this->_tempaiUsers = $tempaiUsers;
        $this->_tempaiIds = array_map(function (PlayerPrimitive $player) {
            return $player->getId();
        }, $tempaiUsers);
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\PlayerPrimitive[]
     */
    public function getTempaiUsers()
    {
        if ($this->_tempaiUsers === null) {
            $this->_tempaiUsers = PlayerPrimitive::findById(
                $this->_db,
                $this->_tempaiIds
            );
            if (empty($this->_tempaiUsers) || count($this->_tempaiUsers) !== count($this->_tempaiIds)) {
                $this->_tempaiUsers = null;
                throw new EntityNotFoundException("Not all players were found in DB (among id#" . implode(',', $this->_tempaiIds));
            }
        }
        return $this->_tempaiUsers;
    }

    /**
     * @param int $uradora
     * @return RoundPrimitive
     */
    public function setUradora($uradora)
    {
        $this->_uradora = $uradora;
        return $this;
    }

    /**
     * @return int
     */
    public function getUradora()
    {
        return $this->_uradora;
    }

    /**
     * @param \Riichi\PlayerPrimitive $winner
     * @return RoundPrimitive
     */
    public function setWinner(PlayerPrimitive $winner)
    {
        $this->_winner = $winner;
        $this->_winnerId = $winner->getId();
        return $this;
    }

    /**
     * @throws EntityNotFoundException
     * @return \Riichi\PlayerPrimitive
     */
    public function getWinner()
    {
        if (!$this->_winner) {
            $foundUsers = PlayerPrimitive::findById($this->_db, [$this->_winnerId]);
            if (empty($foundUsers)) {
                throw new EntityNotFoundException("Entity PlayerPrimitive with id#" . $this->_winnerId . ' not found in DB');
            }
            $this->_winner = $foundUsers[0];
        }
        return $this->_winner;
    }

    /**
     * @return string
     */
    public function getWinnerId()
    {
        return $this->_winnerId;
    }

    /**
     * @param string $yaku
     * @return RoundPrimitive
     */
    public function setYaku($yaku)
    {
        $this->_yaku = $yaku;
        return $this;
    }

    /**
     * @return string
     */
    public function getYaku()
    {
        return $this->_yaku;
    }

    /**
     * @return boolean
     */
    public function getOpenHand()
    {
        return boolval($this->_openHand);
    }

    /**
     * @return SessionState
     * @throws EntityNotFoundException
     * @throws InvalidParametersException
     */
    public function getLastSessionState()
    {
        return SessionState::fromJson(
            $this->getEvent()->getRuleset(),
            $this->getSession()->getPlayersIds(),
            $this->_lastSessionState
        );
    }
}
