<?php
/* TODO-лист
- убрать тест проги в конце
- убрать все echo в коде, любой результат для вывода добавлять в $this->result
- ~122 и ~159 строка, придумать более адекватное решение
- я не уверен насчет того, нужна ли ссылка для $this: &$this; проверить 
- вынести регекспы и стопку ифов в методы
- возможно array_values не нужен, т.к. индекс карт не важен
- урезать кол-во комментов, их чё-то много и сложнее читается код
- убрать все TODO
*/



/*
данный класс является главным и обрабатывает все другие объекты (классы) и отвечает за управление всей игрой
точкой конфигурации является приватный метод init (GameFool), а запуска игры - play
*/
class GameFool
{
    public const MAX_PLAYERS = 4;
    public const MIN_PLAYERS = 2;
    public const START_CARDS_COUNT = 6;

    //массив объектов-игроков
    public $players = [];

    //колода карт игры
    public $deck = null;

    //очередность игроков
    public $playersQueue = [];

    //событие "Игра запущена"
    public $gameStarted = false;

    //ко-во пробелов для вывода результатов раунда
    public $spaces = '';

    //"куча" карт, которые участвовали в раунде
    public $head = [];

    //результат игры, необходим для конструкции echo (new GameFool)()
    private $result = '';

    //номер колоды (рандомный)
    private $deckNumber = 0;



    public function __invoke($obj = null) {
        if ($obj instanceof Player) {
            if (count($this->players) == 4) {
                throw new Exception('Игроков не может быть больше ' . self::MAX_PLAYERS);
            }
            $player = $obj;
            $player->game = &$this;
            $this->players[] = $player;

        } elseif ($obj instanceof CardsDeck) {
            if ($this->deckNumber != null) {
                throw new Exception('Колода карт уже сформирована');
            }
            $deck = $obj;
            $deck->game = &$this;
            $this->deck = $deck;
            $this->deckNumber = $deck->rand;

        } elseif ($obj == null) {
            if (count($this->players) < 2) {
                throw new Exception('Игроков не может быть меньше ' . self::MIN_PLAYERS);
            }

            if ($this->gameStarted) {
                throw new Exception('Игра уже запущена');
            }

            if ($this->deckNumber == null) {
                throw new Exception('Колода карт не сформирована, передайте следующим вызовом объект типа CardsDeck');
            }
            $this->init();
            $this->gameStarted = true;
        } else {
            throw new Exception('Необходимо передать либо объект типа Player | CardsDeck, либо вызвать объект без параметров');
        }

        return $this;
    }

    public function __toString()
    {
        return $this->result;
    }

     //старт игры
     private function init() {
        //необходимо установить заголовок, иначе не срабатывают переносы строк
        header('Content-type: text/plain');

        //создаём очередь игроков для игры
        $this->playersQueue = &$this->players;

        //записываем в результат номер колоды
        $this->addToResult('Deck random: ' . $this->deckNumber);

        //запуск сортировки колоды
        $this->deck->randomSort();

        //раздача карт игрокам
        $this->deck->dealCards();

        //установить карту-козырь
        $this->deck->setTrump();

        //записываем в результат карту-козырь
        $this->addToResult('Trump: ' . $this->deck->trump);

        //сортируем и показываем карты игроков
        foreach ($this->players as $player) {
            $player->sortCards();
            $this->addToResult($player->showCards());
        }
        $this->addToResult("");

        //запускаем раунды
        $this->play();  
    }

    //добавить на вывод строку
    public function addToResult($str) {
        $this->result .= ($this->result != "") ? "\n" . $str : $str;
    }

    //старт игры по раундам
    private function play() {
        for ($i = 1; ; $i++) {
            //берем из очереди первых игроков
            $firstPlayer = array_shift($this->playersQueue);
            $secondPlayer = array_shift($this->playersQueue);

            $secondPlayerLoose = false;

            $roundNumber = (strlen($i) == 1) ? "0$i: " : "$i: ";

            $this->spaces = str_repeat(' ', strlen($roundNumber));
            $this->addToResult(
                $roundNumber . 
                $firstPlayer->name . '(' . implode(',', $firstPlayer->cards) . ') vs ' .
                $secondPlayer->name . '(' . implode(',', $secondPlayer->cards) . ')'
        );

            $firstPlayer->throwCard();

            //какой-то кривой код, я понимаю, но дедлайн по сдаче тз поджимает
            if ($secondPlayer->defend()) {
                while(1) {
                    if ($firstPlayer->tossCard()) {
                        if (!$secondPlayer->defend()) {
                            //игрок отдаёт свои карты того же достоинства
                            //while ($firstPlayer->tossCard());
                            $firstPlayer->giveCards();

                            foreach ($this->heap as $card){
                                $this->addToResult($this->spaces . $secondPlayer->name . ' <-- ' . $card);
                            }

                            //забирает карты из хипа и сортирует карты
                            $secondPlayer->cards = array_merge($secondPlayer->cards, $this->heap);
                            $secondPlayer->sortCards();

                            $secondPlayerLoose = true;
                            break;
                        }
                    } else {
                        break;
                    }
                }
            } else {
                //игрок отдаёт свои карты того же достоинства
                //while ($firstPlayer->tossCard());
                $firstPlayer->giveCards();

                foreach ($this->heap as $card){
                    $this->addToResult($this->spaces .  $secondPlayer->name . ' <-- ' . $card);
                }

                //забирает карты из хипа и сортирует карты
                $secondPlayer->cards = array_merge($secondPlayer->cards, $this->heap);
                $secondPlayer->sortCards();

                $secondPlayerLoose = true;
            }

            $firstPlayer->takeCards();
            $secondPlayer->takeCards();

            //ужасный стэк, он работает только для 3х игроков
            if ($secondPlayerLoose) {
                array_push($this->playersQueue, $firstPlayer, $secondPlayer);
            } else {
                array_unshift($this->playersQueue, $secondPlayer);
                array_push($this->playersQueue, $firstPlayer);
            }

            if (count($firstPlayer->cards) == 0) {
                foreach ($this->playersQueue as $key => $player) {
                    if ($player == $firstPlayer) {
                        //unset($firstPlayer);
                        unset($this->playersQueue[$key]);
                    }
                }
            }

            if (count($secondPlayer->cards) == 0) {
                foreach ($this->playersQueue as $key => $player) {
                    if ($player == $secondPlayer) {
                        //unset($secondPlayer);
                        unset($this->playersQueue[$key]);
                    }
                }
            }

            $this->heap = [];

            if (count($this->playersQueue) == 0) {
                $this->addToResult('');
                $this->addToResult('Fool: -');
                return;
            } elseif (count($this->playersQueue) == 1) {
                $this->addToResult('');
                $player = array_shift($this->playersQueue);
                $this->addToResult('Fool: ' . $player->name);
                return;
            }
            $this->addToResult("");
        }
    }
}

class CardsDeck
{
    //кол-во карт в колоде
    public const DECK_COUNT = 36;

    //рандомный номер колоды (предполагаю, что это что-то типа айдишника игры)
    public $rand = 0;

    //карта-козырь
    public $trump = '';
    
    //объект главного класса
    public $game =  null;
    
    /*
    изначально отсортированная колода карт по масти в следующем порядке ♠, ♥, ♣, ♦; 
    для каждой масти карты упорядочены по достоинству, от 6 до туза
    */
    public $deck = [
        '6♠', '7♠', '8♠', '9♠', '10♠', 'В♠', 'Д♠', 'К♠', 'Т♠',
        '6♥', '7♥', '8♥', '9♥', '10♥', 'В♥', 'Д♥', 'К♥', 'Т♥',
        '6♣', '7♣', '8♣', '9♣', '10♣', 'В♣', 'Д♣', 'К♣', 'Т♣',
        '6♦', '7♦', '8♦', '9♦', '10♦', 'В♦', 'Д♦', 'К♦', 'Т♦'
    ];

    //отмечает событие "Карты перемешаны"
    private $deckSorted = false;

    //отмечает событие "Карты розданы"
    private $cardsDealt = false;




    public function __construct($rand) {
        $this->rand = $rand;
    }

    //рандомная сортировка колоды
    public function randomSort() {
        if ($this->deckSorted) {
            throw new Exception('Карты уже перемешаны');
        }
        for ($i = 0; $i < 1000; $i++) {
            $n = ($this->rand + $i * 2) % self::DECK_COUNT;
            
            /* TODO
            Мне не нравится этот кусок кода, я не придумал более изящного решения, но это работает
            Если не забуду, подумаю, как его переделать
             */
            //рандомная карта для переноса на верхнюю позицию
            $t = $this->deck[$n];
            //удаляем карту из колоды
            unset($this->deck[$n]);
            //пушим ее на топ
            array_unshift($this->deck, $t);
            //ворачиваем индексы отсортированной колоде (0, 1, 2, ...)
            $this->deck = array_values($this->deck);
        }
        $this->deckSorted = true;
    }

    //раздача карт игрокам
    public function dealCards() {
        if ($this->cardsDealt) {
            throw new Exception('Карты были розданы ранее');
        }
        $players = $this->game->players;
        foreach ($players as $player) {
            for ($i = 0; $i < GameFool::START_CARDS_COUNT; $i++) {
                $player->cards[] = array_shift($this->deck);
            }
        }
        
        $this->cardsDealt = true;
    }

    public function setTrump() {
        if ($this->trump != null) {
            throw new Exception('Карта-козырь уже выбрана');
        }
        //TODO да-да, знаю, снова этот кривой код
        $this->trump = array_shift($this->deck);
        array_push($this->deck, $this->trump);
        $this->deck = array_values($this->deck);
    }

    public function getSuit($card) {
        preg_match('/\W+/u', $card, $sign);
        return $sign[0];
    }

    public function getPriority($card) {
        preg_match('/(\d+)|([а-яёА-ЯЁ])/u', $card, $priority);
        return $priority[0];
    }
}

class Player
{
    //имя игрока
    public $name = '';

    //карты игрока; выдаются в начале игры (6 шт)
    public $cards = [];
    
    //объект главного класса
    public $game =  null;




    public function __construct($name) {
        $this->name = $name;
    }

    //бросить самую младшую карту
    public function throwCard() {
        $card = array_shift($this->cards);
        $this->game->heap[] = $card;
        $this->game->addToResult($this->game->spaces . $this->name . ' --> ' . $card);
        $this->sortCards();
    }

    //отбиться
    public function defend() {
        $attackCard = end($this->game->heap);
        $attackCardPriority = $this->game->deck->getPriority($attackCard);
        $attackCardSuit = $this->game->deck->getSuit($attackCard);
        $trump = $this->game->deck->getSuit($this->game->deck->trump);

        foreach ($this->cards as $key => $card) {
            $cardPriority = $this->game->deck->getPriority($card);
            $cardSuit = $this->game->deck->getSuit($card);
            if ($cardPriority > $attackCardPriority && $cardSuit == $attackCardSuit) {
                $this->game->heap[] = $card;
                $this->game->addToResult($this->game->spaces . $card . ' <-- ' . $this->name);
                unset($this->cards[$key]);
                $this->sortCards();
                return true;
            } elseif ($cardSuit == $trump && $attackCardSuit != $cardSuit) {
                $this->game->heap[] = $card;
                $this->game->addToResult($this->game->spaces . $card . ' <-- ' . $this->name);
                unset($this->cards[$key]);
                $this->sortCards();
                return true;
            }
        }
        return false;
    }

    //подбросить карту
    public function tossCard() {
        $highCard = $this->game->deck->getSuit(end($this->cards)) ==  $this->game->deck->getSuit($this->game->deck->trump) ? end($this->cards) : null;
        
        foreach ($this->game->heap as $heapCard) {
            foreach ($this->cards as $key => $playerCard) {
                if ( 
                    $this->game->deck->getPriority($playerCard) == $this->game->deck->getPriority($heapCard) && 
                    (count($this->cards) >= 2) && $playerCard != $highCard
                ) {
                    $this->game->addToResult($this->game->spaces . $this->name . ' --> ' . $playerCard);
                    $this->game->heap[] = $playerCard;
                    unset($this->cards[$key]);
                    $this->sortCards();
                    return true;
                } elseif (
                    (count($this->cards) < 2) && $this->game->deck->getPriority($playerCard) == $highCard
                ) {
                    $this->game->addToResult($this->game->spaces . $this->name . ' --> ' . $playerCard);
                    $this->game->heap[] = $playerCard;
                    unset($this->cards[$key]);
                    $this->sortCards();
                    return true;
                }
            }
        }
        return false;
    }

    //взять недостающие карты из колоды
    public function takeCards() {
        while (count($this->game->deck->deck) && count($this->cards) < GameFool::START_CARDS_COUNT) {
        $card = array_shift($this->game->deck->deck);
        $this->cards[] = $card;
        $this->game->addToResult($this->game->spaces . '(deck) ' . $this->name . ' + ' . $card);
    }
        $this->sortCards();
    }

    public function giveCards() {
        $trumpSign = $this->game->deck->getSuit($this->game->deck->trump);
        $highCard = $this->game->deck->getSuit(end($this->cards)) ==  $this->game->deck->getSuit($this->game->deck->trump) ? end($this->cards) : null;

        foreach ($this->game->heap as $heapCard) {
            foreach ($this->cards as $key => $playerCard) {
                if ($this->game->deck->getPriority($playerCard) == $this->game->deck->getPriority($heapCard) && 
                (count($this->cards) != 1) && $playerCard != $highCard) {
                    $this->game->addToResult($this->game->spaces . $this->name . ' --> ' . $playerCard);
                    $this->game->heap[] = $playerCard;
                    unset($this->cards[$key]);
                    $this->sortCards();
                } elseif (
                    (count($this->cards) == 1) && 
                    $this->game->deck->getSuit($playerCard) == $trumpSign && 
                    $this->game->deck->getPriority($playerCard) == $this->game->deck->getPriority($heapCard)) {
                    $this->game->addToResult($this->game->spaces . $this->name . ' --> ' . $playerCard);
                    $this->game->heap[] = $playerCard;
                    unset($this->cards);
                }
            }
        }
    }

    //сортировка по порядку
    private function sortByPriority(&$arr) {
        $size = count($arr)-1;
        for ($i = $size; $i>=0; $i--) {
          for ($j = 0; $j<=($i-1); $j++) {
              $weight1 = $this->game->deck->getPriority($arr[$j]);
              $weight2 = $this->game->deck->getPriority($arr[$j+1]);
    
              if ($weight1 == 'В') $weight1 = 11;
              elseif ($weight1 == 'Д') $weight1 = 12;
              elseif ($weight1 == 'К') $weight1 = 13;
              elseif ($weight1 == 'Т') $weight1 = 14;

              if ($weight2 == 'В') $weight2 = 11;
              elseif ($weight2 == 'Д') $weight2 = 12;
              elseif ($weight2 == 'К') $weight2 = 13;
              elseif ($weight2 == 'Т') $weight2 = 14;

              if ($weight1>$weight2) {
                  $k = $arr[$j];
                  $arr[$j] = $arr[$j+1];
                  $arr[$j+1] = $k;
              }
            }
        }
    }

    //сортировка по масти
    private function sortBySuit(&$arr) {
        $priority = [
            '♠' => 1,
            '♣' => 2,
            '♦' => 3,
            '♥' => 4,
        ];
        $size = count($arr)-1;
        for ($i = $size; $i>=0; $i--) {
          for ($j = 0; $j<=($i-1); $j++) {
            $weight1 = $this->game->deck->getPriority($arr[$j]);
            $weight2 = $this->game->deck->getPriority($arr[$j+1]);

            if ($weight1 == 'В') $weight1 = 11;
            elseif ($weight1 == 'Д') $weight1 = 12;
            elseif ($weight1 == 'К') $weight1 = 13;
            elseif ($weight1 == 'Т') $weight1 = 14;

            if ($weight2 == 'В') $weight2 = 11;
            elseif ($weight2 == 'Д') $weight2 = 12;
            elseif ($weight2 == 'К') $weight2 = 13;
            elseif ($weight2 == 'Т') $weight2 = 14;

            if ($weight1 != $weight2) {
                continue;
            }

            $sign1 = $this->game->deck->getSuit($arr[$j]);
            $sign2 = $this->game->deck->getSuit($arr[$j+1]);

            if ($priority[$sign1]>$priority[$sign2]) {
                $k = $arr[$j];
                $arr[$j] = $arr[$j+1];
                $arr[$j+1] = $k;
            }
            }
        }
    }

    /*
    сортировка карт игрока по достоинству и по масти (порядок: ♠, ♣, ♦, ♥), затем козыри, также сортированные по достоинству;
    */
    public function sortCards() {
        //находим символ козыря
        $trumpSign = $this->game->deck->getSuit($this->game->deck->trump);

        //отбираем козыри в отдельный массив, чтобы не мешались, а остальные - в другой
        $trumps = [];
        $otherCards = [];
        foreach ($this->cards as $card) {
            $sign = $this->game->deck->getSuit($card);
            if ($sign == $trumpSign) {
                $trumps[] = $card;
                continue;
            }
            $otherCards[] = $card;
        }

        //сортируем козыри по приоритету
        $this->sortByPriority($trumps);
        
        //сортируем основные карты по приоритету, потом по масти
        $this->sortByPriority($otherCards);
        $this->sortBySuit($otherCards);

        //объединим простые карты с козырными
        $this->cards = array_merge($otherCards, $trumps);
    }

    public function showCards() {
        return $this->name . ': ' . implode(",", $this->cards);
    }
}


/* ---------- Тестим наше чудо ------------ */
$a = (new GameFool())(new CardsDeck(24617))(new Player('Rick'))(new Player('Morty'))(new Player('Summer'))();
echo $a;
// echo '<pre>';
// print_r($a);
// echo '</pre>';
?>
