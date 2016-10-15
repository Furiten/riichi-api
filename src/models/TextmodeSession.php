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

/*
    Input example:

    [player][:][(-)?\d{,5}] [player][:][(-)?\d{,5}] [player][:][(-)?\d{,5}] [player][:][(-)?\d{,5}]
    ron [player] from [player] [5-12]han
    ron [player] from [player] [1-4]han \d{2,3}fu
    ron [player] from [player] yakuman
    tsumo [player] [5-12]han
    tsumo [player] [1-4]han \d{2,3}fu
    tsumo [player] yakuman
    draw tempai nobody
    draw tempai [player]
    draw tempai [player] [player]
    draw tempai [player] [player] [player]
    draw tempai all
    chombo [player]
*/

require_once __DIR__ . '/../helpers/Tokenizer.php';

class ParseException extends \Exception {}

class TextmodeSessionModel
{
    /**
     * @var $db
     */
    protected $_db;

    public function __construct(Db $db)
    {
        $this->_db = $db;
    }


    public function addGame($eventId, $gameLog)
    {
        $event = EventPrimitive::findById($this->_db, [$eventId]);
        if (empty($event)) {
            throw new InvalidParametersException('Event id#' . $eventId . ' not found in DB');
        }

        $gameLog = trim($gameLog);
        if (empty($gameLog)) {
            throw new MalformedPayloadException('Game log is empty');
        }

        $tokenizer = new Tokenizer();
        foreach ($tokenizer->tokenize($gameLog) as $statement) {
            $this->_parseStatement($statement);
        }

        return [
            'scores' => $this->_resultScores,
            'counts' => $this->_counts
        ];
    }

    /**
     * Сюда прилетают 100% лексически (и отчасти синтаксически) валидные выражения.
     * Надо их проверить и распарсить
     * @param $statement Token[]
     * @throws ParseException
     */
    protected function _parseStatement($statement)
    {
        if ($statement[0]->type() == Tokenizer::USER_ALIAS) {
            // Первая строка с очками. Пробуем парсить.
            while (!empty($statement)) {
                /** @var $player Token */
                $player = array_shift($statement);
                /** @var $delimiter Token */
                $delimiter = array_shift($statement);
                /** @var $score Token */
                $score = array_shift($statement);

                if ($player->type() != Tokenizer::USER_ALIAS || $delimiter->type() != Tokenizer::SCORE_DELIMITER || $score->type() != Tokenizer::SCORE) {
                    throw new ParseException("Ошибка при вводе данных: некорректный формат строки очков:
                        {$player} {$delimiter} {$score}", 106);
                }

                if (empty($this->_registeredUsers[$player->token()])) {
                    throw new ParseException("Ошибка при вводе данных: игрок {$player} не зарегистрирован", 101);
                }

                $this->_resultScores[$player->token()] = $score;
            }

            $this->_players = array_keys($this->_resultScores);
            if ($this->_calc) {
                $this->_calc->setPlayersList($this->_players);
            }

            if (count($this->_resultScores) != 4) { // TODO: Изменить условие, если будет хиросима :)
                throw new ParseException("Ошибка при вводе данных: количество указанных игроков не равно 4", 100);
            }

            return;
        }

        if ($statement[0]->type() == Tokenizer::OUTCOME) {
            // Строка с записью раунда. Пробуем парсить.
            $methodName = '_parseOutcome' . ucfirst($statement[0]->token());
            if (!is_callable([$this, $methodName])) {
                throw new ParseException("Не удалось разобрать исход ({$statement[0]->token()}: {$methodName})", 106);
            }

            $this->$methodName($statement, $this->_resultScores);
            return;
        }

        $string = array_reduce($statement, function ($acc, $el) {
            return $acc . ' ' . $el;
        }, '');
        throw new ParseException("Ошибка при вводе данных: не удалось разобрать начало строки: " . $string, 202);
    }

    /**
     * @param $tokens Token[]
     * @param $type
     * @return Token
     */
    protected function _findByType($tokens, $type) {
        foreach ($tokens as $v) {
            if ($v->type() == $type) {
                return $v;
            }
        }

        return new Token(null, Tokenizer::UNKNOWN_TOKEN, [], null);
    }

    /**
     * @param $tokens Token[]
     * @param $participants string[]
     * @return Token[]
     * @throws ParseException
     */
    protected function _getRiichi($tokens, $participants) {
        $riichi = [];
        $started = false;
        foreach ($tokens as $v) {
            if ($v->type() == Tokenizer::RIICHI_DELIMITER) {
                $started = true;
                continue;
            }

            if ($started) {
                if ($v->type() == Tokenizer::USER_ALIAS) {
                    if (empty($participants[$v->token()])) {
                        throw new ParseException("Не удалось распарсить риичи. Игрок {$v->token()} не указан в заголовке лога. Опечатка?", 107);
                    }
                    $riichi []= $v->token();
                    $this->_riichi ++;
                } else {
                    return $riichi;
                }
            }
        }

        if ($started && empty($riichi)) {
            throw new ParseException('Не удалось распознать риичи.', 108);
        }
        return $riichi;
    }

    /**
     * @param $tokens Token[]
     * @param $participants string[]
     * @return Token[]
     * @throws ParseException
     */
    protected function _getTempai($tokens, $participants) {
        $tempai = [];
        $started = false;
        foreach ($tokens as $v) {
            if ($v->type() == Tokenizer::TEMPAI) {
                $started = true;
                continue;
            }

            if ($started) {
                if ($v->type() == Tokenizer::USER_ALIAS) {
                    if (empty($participants[$v->token()])) {
                        throw new ParseException("Не удалось распарсить темпай. Игрок {$v->token()} не указан в заголовке лога. Опечатка?", 117);
                    }
                    $tempai []= $v->token();
                } else if ($v->type() == Tokenizer::ALL) {
                    if (!empty($tempai)) {
                        throw new ParseException("Не удалось распарсить темпай. Неожиданное ключевое слово 'all'. Опечатка?", 119);
                    }
                    return array_keys($participants);
                } else if ($v->type() == Tokenizer::NOBODY) {
                    if (!empty($tempai)) {
                        throw new ParseException("Не удалось распарсить темпай. Неожиданное ключевое слово 'nobody'. Опечатка?", 120);
                    }
                    return [];
                } else {
                    return $tempai;
                }
            }
        }

        if (empty($tempai)) {
            throw new ParseException('Не удалось распознать темпай: не распознаны игроки.', 118);
        }
        return $tempai;
    }

    protected function _parseOutcomeRon($tokens, $participants)
    {
        // check if double/triple ron occured
        $multiRon = !!$this->_findByType($tokens, Tokenizer::ALSO)->token();
        if ($multiRon) {
            $this->_parseOutcomeMultiRon($tokens, $participants);
        } else {
            $this->_parseOutcomeSingleRon($tokens, $participants);
        }
    }

    protected function _parseOutcomeSingleRon($tokens, $participants) {
        /** @var $winner Token
         * @var $from Token
         * @var $loser Token */
        list(/*ron*/, $winner, $from, $loser) = $tokens;
        if (empty($participants[$winner->token()])) {
            throw new ParseException("Игрок {$winner} не указан в заголовке лога. Опечатка?", 104);
        }
        if ($from->type() != Tokenizer::FROM) {
            throw new ParseException("Не указан игрок, с которого взят рон", 103);
        }
        if (empty($participants[$loser->token()])) {
            throw new ParseException("Игрок {$loser} не указан в заголовке лога. Опечатка?", 105);
        }
        $yakuParsed = $this->_parseYaku($tokens);
        $resultData = [
            'outcome' => 'ron',
            'multiRon' => false,
            'round' => $this->_currentRound,
            'winner' => $winner->token(),
            'loser' => $loser->token(),
            'honba' => $this->_honba,
            'han' => $this->_findByType($tokens, Tokenizer::HAN_COUNT)->clean(),
            'fu' => $this->_findByType($tokens, Tokenizer::FU_COUNT)->clean(),
            'yakuman' => !!$this->_findByType($tokens, Tokenizer::YAKUMAN)->token(),
            'yakuList' => $yakuParsed['yaku'],
            'doraCount' => $yakuParsed['dora'],
            'riichi' => $this->_getRiichi($tokens, $participants),
            'dealer' => $this->_checkDealer($winner)
        ];

        if ($this->_calc) {
            $this->_calc->registerRon(
                $resultData['han'],
                $resultData['fu'],
                $winner,
                $loser,
                $this->_honba,
                $resultData['riichi'],
                $resultData['riichi_totalCount'],
                $this->_players[$this->_currentDealer % 4],
                !empty($resultData['yakuman'])
            );
        }

        $this->_counts['ron']++;
        if (!empty($resultData['yakuman'])) {
            $resultData['han'] = 13; // TODO: remove
            $this->_counts['yakuman']++;
        }
    }

    /**
     * @param $tokens Token[]
     * @return array
     * @throws ParseException
     */
    protected function _splitMultiRon($tokens)
    {
        /** @var $loser Token
         *  @var $from Token */
        list(/*ron*/, /*winner*/, $from, $loser) = $tokens;
        if ($from->type() != Tokenizer::FROM) {
            throw new ParseException("Не указан игрок, с которого взят рон", 103);
        }

        $chunks = [[]];
        $idx = 0;
        foreach ($tokens as $k => $t) {
            if (
                $t->type() == Tokenizer::OUTCOME ||
                $t->type() == Tokenizer::FROM
            ) continue; // unify statements, cut unused keywords

            if (
                $k > 0 &&
                $tokens[$k-1]->type() == Tokenizer::FROM &&
                $t->type() == Tokenizer::USER_ALIAS &&
                $t->token() == $loser->token()
            ) continue; // saved separately

            if ($t->type() == Tokenizer::ALSO) {
                $idx ++;
                $chunks []= [];
                continue;
            }
            $chunks[$idx] []= $t;
        }

        return [$chunks, $loser];
    }

    /**
     * TODO: move this to SessionState
     *
     * @param $rons Token[][]
     * @param $loser Token
     * @param $participants
     * @return array
     * @throws ParseException
     */
    protected function _assignRiichiBets($rons, $loser, $participants) {
        $riichiOnTable = $this->_riichi; // save this one as it's erased with this->_getRiichi
        $bets = [];
        $winners = [];

        /** @var $ron Token[] */
        foreach ($rons as $ron) {
            $winners[$ron[0]->token()] = [];
            $bets = array_merge($bets, $this->_getRiichi($ron, $participants));
            foreach ($bets as $k => $player) {
                if (isset($winners[$player])) {
                    $winners[$player] []= $ron[0]->token(); // winner always gets back his bet
                    unset($bets[$k]);
                }
            }
        }

        // Find player who gets non-winning riichi bets
        $playersRing = array_merge(array_keys($participants), array_keys($participants)); // double the array to form a ring
        $closestWinner = null;
        for ($i = 0; $i < count($playersRing); $i++) {
            if ($loser->token() == $playersRing[$i]) {
                for ($j = $i + 1; $j < count($playersRing); $j++) {
                    if (isset($winners[$playersRing[$j]])) {
                        $closestWinner = $playersRing[$j];
                        break 2;
                    }
                }
            }
        }

        if (!$closestWinner) {
            throw new ParseException('Не найден ближайший победитель для риичи-ставок: такого не должно было произойти!', 119);
        }

        $winners[$closestWinner] = array_merge($winners[$closestWinner], $bets);

        // assign riichi counts, add riichi on table for first winner
        foreach ($winners as $name => $bets) {
            if ($name == $closestWinner) {
                $winners[$name] = [
                    'riichi_totalCount' => $riichiOnTable + count($winners[$name]),
                    'riichi' => $winners[$name]
                ];
            } else {
                $winners[$name] = [
                    'riichi_totalCount' => count($winners[$name]),
                    'riichi' => $winners[$name]
                ];
            }
        }

        return $winners;
    }

    protected function _parseOutcomeMultiRon($tokens, $participants)
    {
        /** @var $loser Token */
        list($rons, $loser) = $this->_splitMultiRon($tokens);
        if (empty($participants[$loser->token()])) {
            throw new ParseException("Игрок {$loser} не указан в заголовке лога. Опечатка?", 105);
        }

        $riichiGoesTo = $this->_assignRiichiBets($rons, $loser, $participants);

        foreach ($rons as $ron) {
            /** @var $winner Token */
            $winner = $ron[0];
            if (empty($participants[$winner->token()])) {
                throw new ParseException("Игрок {$winner} не указан в заголовке лога. Опечатка?", 104);
            }

            $yakuParsed = $this->_parseYaku($ron);
            $resultData = [
                'outcome' => 'ron',
                'multiRon' => count($rons),
                'round' => $this->_currentRound,
                'winner' => $winner->token(),
                'loser' => $loser->token(),
                'honba' => $this->_honba,
                'han' => $this->_findByType($ron, Tokenizer::HAN_COUNT)->clean(),
                'fu' => $this->_findByType($ron, Tokenizer::FU_COUNT)->clean(),
                'yakuman' => !!$this->_findByType($ron, Tokenizer::YAKUMAN)->token(),
                'yakuList' => $yakuParsed['yaku'],
                'doraCount' => $yakuParsed['dora'],
                'dealer' => $this->_checkDealer($winner)
            ];
            $resultData = array_merge($resultData, $riichiGoesTo[$winner->token()]);

            if ($this->_calc) {
                $this->_calc->registerRon(
                    $resultData['han'],
                    $resultData['fu'],
                    $winner,
                    $loser,
                    $this->_honba,
                    $resultData['riichi'],
                    $resultData['riichi_totalCount'],
                    $this->_players[$this->_currentDealer % 4],
                    !empty($resultData['yakuman'])
                );
            }

            if (!empty($resultData['yakuman'])) {
                $resultData['han'] = 13; // TODO: remove
                $this->_counts['yakuman']++;
            } else {
                $this->_counts['ron']++;
            }
        }

        if (count($rons) == 2) $this->_counts['doubleRon']++;
        if (count($rons) == 3) $this->_counts['tripleRon']++;
    }

    protected function _parseOutcomeTsumo($tokens, $participants)
    {
        /** @var $winner Token */
        list(/*tsumo*/, $winner) = $tokens;
        if (empty($participants[$winner->token()])) {
            throw new ParseException("Игрок {$winner} не указан в заголовке лога. Опечатка?", 104);
        }

        $yakuParsed = $this->_parseYaku($tokens);
        $resultData = [
            'outcome' => 'tsumo',
            'multiRon' => false,
            'round' => $this->_currentRound,
            'winner' => $winner->token(),
            'honba' => $this->_honba,
            'han' => $this->_findByType($tokens, Tokenizer::HAN_COUNT)->clean(),
            'fu' => $this->_findByType($tokens, Tokenizer::FU_COUNT)->clean(),
            'yakuman' => !!$this->_findByType($tokens, Tokenizer::YAKUMAN)->token(),
            'yakuList' => $yakuParsed['yaku'],
            'doraCount' => $yakuParsed['dora'],
            'dealer' => $this->_checkDealer($winner),
            'riichi' => $this->_getRiichi($tokens, $participants)
        ];
        $resultData['riichi_totalCount'] = $this->_riichi;
        $this->_riichi = 0;

        if ($this->_calc) {
            $this->_calc->registerTsumo(
                $resultData['han'],
                $resultData['fu'],
                $winner,
                $this->_honba,
                $resultData['riichi'],
                $resultData['riichi_totalCount'],
                $this->_players[$this->_currentDealer % 4],
                !empty($resultData['yakuman'])
            );
        }

        $this->_counts['tsumo']++;
        if (!empty($resultData['yakuman'])) {
            $resultData['han'] = 13; // TODO: remove
            $this->_counts['yakuman']++;
            call_user_func_array($this->_yakuman, array($resultData));
        } else {
            call_user_func_array($this->_usualWin, array($resultData));
        }
    }

    protected function _parseOutcomeDraw($tokens, $participants)
    {
        $tempaiPlayers = $this->_getTempai($tokens, $participants);
        $playersStatus = array_combine(
            array_keys($participants),
            ['noten', 'noten', 'noten', 'noten']
        );

        if (!empty($tempaiPlayers)) {
            $playersStatus = array_merge(
                $playersStatus,
                array_combine(
                    $tempaiPlayers,
                    array_fill(0, count($tempaiPlayers), 'tempai')
                )
            );
        }

        $resultData = [
            'outcome' => 'draw',
            'round' => $this->_currentRound,
            'honba' => $this->_honba,
            'riichi' => $this->_getRiichi($tokens, $participants),
            'riichi_totalCount' => $this->_riichi,
            'players_tempai' => $playersStatus
        ];

        if ($this->_calc) {
            $this->_calc->registerDraw(
                $playersStatus,
                $resultData['riichi']
            );
        }

        if ($playersStatus[$this->_players[$this->_currentDealer % 4]] != 'tempai') {
            $this->_currentDealer++;
            $this->_currentRound++;
        }

        $this->_counts['draw']++;
    }

    protected function _parseOutcomeChombo($tokens, $participants)
    {
        /** @var $loser Token */
        list(/*chombo*/, $loser) = $tokens;
        if (empty($participants[$loser->token()])) {
            throw new ParseException("Игрок {$loser} не указан в заголовке лога. Опечатка?", 104);
        }

        $resultData = [
            'outcome' => 'chombo',
            'round' => $this->_currentRound,
            'loser' => $loser->token(),
            'dealer' => $this->_checkDealer($loser)
        ];

        if ($this->_calc) {
            $this->_calc->registerChombo(
                $loser,
                $this->_players[$this->_currentDealer % 4]
            );
        }

        $this->_counts['chombo']++;
    }

    /**
     * @param $tokens Token[]
     * @return array
     * @throws ParseException
     * @throws TokenizerException
     */
    protected function _parseYaku($tokens)
    {
        if (!$this->_findByType($tokens, Tokenizer::YAKU_START)->token()) {
            return [
                'yaku' => [],
                'dora' => '0'
            ]; // no yaku info
        }

        $yakuStarted = false;
        $yaku = [];
        $doraCount = 0;
        foreach ($tokens as $t) {
            if ($t->type() == Tokenizer::YAKU_START) {
                $yakuStarted = true;
                continue;
            }

            if ($t->type() == Tokenizer::YAKU_END) {
                $yakuStarted = false;
                break;
            }

            if ($yakuStarted && $t->type() == Tokenizer::YAKU) {
                $yaku []= $t;
            }

            if ($yakuStarted && $t->type() == Tokenizer::DORA_DELIMITER) {
                $doraCount = '1'; // means dora 1 if there is only delimiter
            }

            if ($doraCount == '1' && $yakuStarted && $t->type() == Tokenizer::DORA_COUNT) {
                $doraCount = $t->token();
            }
        }

        if ($yakuStarted) {
            throw new ParseException('Не найдено окончание списка яку', 210);
        }

        return [
            'yaku' => array_map(function(Token $yaku) {
                if ($yaku->type() != Tokenizer::YAKU) {
                    throw new TokenizerException('Requested token #' . $yaku->token() . ' is not yaku', 211);
                }

                $id = Tokenizer::identifyYakuByName($yaku->token());
                if (!$id) {
                    throw new TokenizerException('No id found for requested yaku #' . $yaku->token() .
                        ', this should not happen!', 212);
                }

                return $id;
            }, $yaku), 
            'dora' => $doraCount ? $doraCount : '0'
        ];
    }

    //<editor-fold desc="For testing only!!!">
    public function _iGetRiichi($tokens, $participants)
    {
        return $this->_getRiichi($tokens, $participants);
    }

    public function _iGetTempai($tokens, $participants)
    {
        return $this->_getTempai($tokens, $participants);
    }

    public function _iSplitMultiRon($tokens)
    {
        return $this->_splitMultiRon($tokens);
    }

    public function _iAssignRiichiBets($tokens, $participants)
    {
        list($rons, $loser) = $this->_splitMultiRon($tokens);
        return $this->_assignRiichiBets($rons, $loser, $participants);
    }
    //</editor-fold>
}
