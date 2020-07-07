<?php
/* TODO-лист
- убрать тест проги в конце
- убрать все echo в коде, любой результат для вывода добавлять в $this->result
- ~122 и ~159 строка, придумать более адекватное решение
- я не уверен насчет того, нужна ли ссылка для $this: &$this; проверить 
- вынести регекспы и стопку ифов в методы
- возможно array_values не нужен, т.к. индекс карт не важен
- урезать кол-во комментов, их чё-то много и сложнее читается код
*/



/*
данный класс является главным и обрабатывает все другие объекты (классы) и отвечает за управление всей игрой
точкой запуска является приватный метод run (GameFool)
*/
class GameFool
{
    public const MAX_PLAYERS = 4;
    public const MIN_PLAYERS = 2;
    public const START_CARDS_COUNT = 6;

    //массив объектов-игроков
    public $players = [];

    //колода карт игры
    public $deck = NULL;

    //результат игры, необходим для конструкции echo (new GameFool)(), возвращается в run'е
    private $result = '';

    //номер колоды (рандомный)
    private $deckNumber = 0;




    public function __invoke($obj = NULL) {
        if ($obj instanceof Player) {
            if (count($this->players) == 4) {
                throw new Exception('Игроков не может быть больше ' . self::MAX_PLAYERS);
            }
            $player = $obj;
            $player->game = &$this;
            $this->players[] = $player;

        } elseif ($obj instanceof CardsDeck) {
            if ($this->deckNumber != NULL) {
                throw new Exception('Колода карт уже сформирована');
            }
            $deck = $obj;
            $deck->game = &$this;
            $this->deck = $deck;
            $this->deckNumber = $deck->rand;

        } elseif ($obj == NULL) {
            if (count($this->players) < 2) {
                throw new Exception('Игроков не может быть меньше ' . self::MIN_PLAYERS);
            }

            if ($this->deckNumber == NULL) {
                throw new Exception('Колода карт не сформирована, передайте следующим вызовом объект типа CardsDeck');
            }
            $this->run();
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
     private function run() {

        //необходимо установить заголовок, иначе не срабатывают переносы строк
        header('Content-type: text/plain');

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
        
        //возвращаем результат игры
        return $this->result;
    }

    //добавить на вывод строку
    private function addToResult($str) {
        $this->result .= ($this->result != "") ? "\n" . $str : $str;
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
    public $game =  NULL;
    
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
            //прокидываем игроков, чтобы в дальнейшем с ними работать
            $players = $this->game->players;
            for ($i = 0; $i < GameFool::START_CARDS_COUNT; $i++) {
                foreach ($players as $player) {
                    $player->cards[] = array_shift($this->deck);
                }
            }
            $this->cardsDealt = true;
        }

    public function setTrump() {
        if ($this->trump != NULL) {
            throw new Exception('Карта-козырь уже выбрана');
        }
        //TODO да-да, знаю, снова этот кривой код
        $this->trump = array_shift($this->deck);
        array_push($this->deck, $this->trump);
        $this->deck = array_values($this->deck);
    }
}

class Player
{
    //имя игрока
    public $name;

    //карты игрока; выдаются в начале игры (6 шт)
    public $cards = [];
    
    //объект главного класса
    public $game =  NULL;




    public function __construct($name) {
        $this->name = $name;
    }

    //сортировка по порядку
    private function sortByPriority(&$arr) {
        $size = count($arr)-1;
        for ($i = $size; $i>=0; $i--) {
          for ($j = 0; $j<=($i-1); $j++) {
                preg_match('/(\d+)|([а-яёА-ЯЁ])/u', $arr[$j], $weight1);
                preg_match('/(\d+)|([а-яёА-ЯЁ])/u', $arr[$j+1], $weight2);
                $weight1 = $weight1[0];
                $weight2 = $weight2[0];
    
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
                preg_match('/(\d+)|([а-яёА-ЯЁ])/u', $arr[$j], $weight1);
                preg_match('/(\d+)|([а-яёА-ЯЁ])/u', $arr[$j+1], $weight2);
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

                preg_match('/\W+/u', $arr[$j], $sign1);
                preg_match('/\W+/u', $arr[$j+1], $sign2);
                $sign1 = $sign1[0];
                $sign2 = $sign2[0];
    
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
        preg_match('/\W+/u', $this->game->deck->trump, $trumpSign);

        //отбираем козыри в отдельный массив, чтобы не мешались, а остальные - в другой
        $trumps = [];
        $otherCards = [];
        foreach ($this->cards as $card) {
            preg_match('/\W+/u', $card, $sign);
            if ($sign[0] == $trumpSign[0]) {
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
$a = (new GameFool())(new CardsDeck(rand(1, 0xffff)))(new Player('Rick'))(new Player('Morty'))(new Player('Tom'))();
echo $a;
// echo '<pre>';
// print_r($a);
// echo '</pre>';
?>