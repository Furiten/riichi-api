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

require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../primitives/PlayerRegistration.php';
require_once __DIR__ . '/../Controller.php';

class EventsController extends Controller
{
    /**
     * @param string $title
     * @param string $description
     * @param string $type either 'online' or 'offline' or 'interactive_offline'
     * @param string $ruleset one of possible ruleset names ('ema', 'jpmlA', 'tenhounet', or any other supported by system)
     * @param int $gameDuration duration of game in this event
     * @throws BadActionException
     * @return int
     */
    public function createEvent($title, $description, $type, $ruleset, $gameDuration)
    {
        $this->_log->addInfo('Creating new [' . $type . '] event with [' . $ruleset . '] rules');

        $event = (new EventPrimitive($this->_db))
            ->setTitle($title)
            ->setDescription($description)
            ->setType($type)
            ->setGameDuration($gameDuration)
            ->setRuleset(Ruleset::instance($ruleset))
            // ->setStartTime('')   // TODO
            // ->setEndTime('')     // TODO
        ;
        $success = $event->save();
        if (!$success) {
            throw new BadActionException('Somehow we couldn\'t create event - this should not happen');
        }

        $this->_log->addInfo('Successfully create new event (id# ' . $event->getId() . ')');
        return $event->getId();
    }

    /**
     * Get all players registered for event
     *
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return array
     */
    public function getAllRegisteredPlayers($eventId)
    {
        $this->_log->addInfo('Getting all players for event id# ' . $eventId);

        $players = PlayerRegistrationPrimitive::findRegisteredPlayersByEvent($this->_db, $eventId);
        $data = array_map(function (PlayerPrimitive $p) {
            return [
                'id'            => $p->getId(),
                'display_name'  => $p->getDisplayName(),
                'alias'         => $p->getAlias(),
                'tenhou_id'     => $p->getTenhouId()
            ];
        }, $players);

        $this->_log->addInfo('Successfully received all players for event id# ' . $eventId);
        return $data;
    }

    /**
     * Get all players registered for event
     *
     * @throws InvalidParametersException
     * @return array
     */
    public function getAllRegisteredPlayersFromToken()
    {
        $this->_log->addInfo('Getting all players for event (by token)');
        $data = (new EventModel($this->_db, $this->_config))->dataFromToken();
        if (empty($data)) {
            throw new InvalidParametersException('Invalid user token');
        }
        return $this->getAllRegisteredPlayers($data->getEventId());
    }

    /**
     * Get tables state in tournament
     *
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return array
     */
    public function getTablesState($eventId)
    {
        $this->_log->addInfo('Getting tables state for event #' . $eventId);
        $data = (new EventModel($this->_db, $this->_config))
            ->getTablesState($eventId);
        $this->_log->addInfo('Successfully got tables state for event #' . $eventId);
        return $data;
    }

    /**
     * Get current seating in tournament
     *
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return array
     */
    public function getCurrentSeating($eventId)
    {
        $this->_log->addInfo('Getting current seating for event #' . $eventId);
        $data = (new EventModel($this->_db, $this->_config))
            ->getCurrentSeating($eventId);
        $this->_log->addInfo('Successfully got current seating for event #' . $eventId);
        return $data;
    }

    /**
     * Register for participation in event
     *
     * @param integer $pin
     * @throws InvalidParametersException
     * @return string Auth token
     */
    public function registerPlayer($pin)
    {
        $this->_log->addInfo('Registering pin code #' . $pin);
        $authToken = (new EventModel($this->_db, $this->_config))
            ->registerPlayer($pin);
        $this->_log->addInfo('Successfully registered pin code #' . $pin);
        return $authToken;
    }

    /**
     * @param integer $playerId
     * @param integer $eventId
     * @throws AuthFailedException
     * @throws BadActionException
     * @throws InvalidParametersException
     * @return string Secret pin code for self-registration
     */
    public function enrollPlayer($playerId, $eventId)
    {
        $this->_log->addInfo('Enrolling player id# ' . $playerId . ' for event id# ' . $eventId);
        $pin = (new EventModel($this->_db, $this->_config))
            ->enrollPlayer($eventId, $playerId);
        $this->_log->addInfo('Successfully enrolled player id# ' . $playerId . ' for event id# ' . $eventId);
        return $pin;
    }

    /**
     * Get event rules configuration
     *
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return array
     */
    public function getGameConfig($eventId)
    {
        $this->_log->addInfo('Getting config for event id# ' . $eventId);

        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        $rules = $event[0]->getRuleset();
        $data = [
            'allowedYaku'       => $rules->allowedYaku(),
            'startPoints'       => $rules->startPoints(),
            'withKazoe'         => $rules->withKazoe(),
            'withKiriageMangan' => $rules->withKiriageMangan(),
            'withAbortives'     => $rules->withAbortives(),
            'withNagashiMangan' => $rules->withNagashiMangan()
        ];

        $this->_log->addInfo('Successfully received config for event id# ' . $eventId);
        return $data;
    }

    /**
     * Get event rules configuration
     *
     * @throws InvalidParametersException
     * @return array
     */
    public function getGameConfigFromToken()
    {
        $this->_log->addInfo('Getting config for event (by token)');
        $data = (new EventModel($this->_db, $this->_config))->dataFromToken();
        if (empty($data)) {
            throw new InvalidParametersException('Invalid user token');
        }
        return $this->getGameConfig($data->getEventId());
    }

    /**
     * Get rating table for event
     *
     * @param integer $eventId
     * @param string $orderBy  either 'name', 'rating' or 'avg_place'
     * @param string $order  either 'asc' or 'desc'
     * @throws InvalidParametersException
     * @return array
     */
    public function getRatingTable($eventId, $orderBy, $order)
    {
        $this->_log->addInfo('Getting rating table for event id# ' . $eventId);

        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        $table = (new EventModel($this->_db, $this->_config))
            ->getRatingTable($event[0], $orderBy, $order);

        $this->_log->addInfo('Successfully received rating table for event id# ' . $eventId);
        return $table;
    }

    /**
     * Get last games sorted by date (latest go first)
     *
     * @param integer $eventId
     * @param integer $limit
     * @param integer $offset
     * @throws InvalidParametersException
     * @return array
     */
    public function getLastGames($eventId, $limit, $offset)
    {
        $this->_log->addInfo('Getting games list [' . $limit . '/' . $offset . '] for event id# ' . $eventId);

        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        $table = (new EventModel($this->_db, $this->_config))
            ->getLastFinishedGames($event[0], $limit, $offset);

        $this->_log->addInfo('Successfully got games list [' . $limit . '/' . $offset . '] for event id# ' . $eventId);
        return $table;
    }

    /**
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return array
     */
    public function getTimerState($eventId)
    {
        $this->_log->addInfo('Getting timer for event id#' . $eventId);

        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        // default: game finished
        $response = [
            'started' => false,
            'finished' => true,
            'time_remaining' => null
        ];

        if (empty($event[0]->getLastTimer())) {
            // no timer started
            $response = [
                'started' => false,
                'finished' => false,
                'time_remaining' => null
            ];
        } else if ($event[0]->getLastTimer() + $event[0]->getGameDuration() * 60 > time()) {
            // game in progress
            $response = [
                'started' => true,
                'finished' => false,
                'time_remaining' => $event[0]->getLastTimer() + $event[0]->getGameDuration() * 60 - time()
            ];
        }

        $this->_log->addInfo('Successfully got timer data for event id#' . $eventId);

        return $response;
    }

    /**
     * @throws InvalidParametersException
     * @return array
     */
    public function getTimerStateFromToken()
    {
        $this->_log->addInfo('Getting timer for event (by token)');
        $data = (new EventModel($this->_db, $this->_config))->dataFromToken();
        if (empty($data)) {
            throw new InvalidParametersException('Invalid user token');
        }
        return $this->getTimerState($data->getEventId());
    }

    /**
     * @param integer $eventId
     * @throws InvalidParametersException
     * @return bool
     */
    public function startTimer($eventId)
    {
        $this->_log->addInfo('Starting timer for event id#' . $eventId);

        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        $success = $event[0]->setLastTimer(time())->save();
        $this->_log->addInfo('Successfully started timer for event id#' . $eventId);
        return $success;
    }
}
