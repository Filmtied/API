<?php

    require_once 'FilmTiedApi.php';

    $token = '#someSecredToken#';

    $params = array(
        'apiServerAddress'      => 'http://api.filmtied.com',
        'cache'                 => 'memcache',
        'cacheServerAddress'    => '127.0.0.1',
        'cacheServerPort'       => '11211',
    );

    $filmTiedApi = new FilmTiedApi($token, $params);

    $url = $filmTiedApi->changeUrl('http://www.imdb.com/name/nm0000295/');
    var_dump($url);
    echo '<hr>';

    $data = $filmTiedApi->get('http://www.filmtied.com/The-Godfather');
    var_dump($data);
    echo '<hr>';

    $data = $filmTiedApi->get('http://www.filmtied.com/The-Godfather', 1);
    var_dump($data);
    echo '<hr>';

    $data = $filmTiedApi->get('http://www.imdb.com/title/tt0119008/');
    var_dump($data);
    echo '<hr>';

    $data = $filmTiedApi->search('Godfather');
    var_dump($data);
    echo '<hr>';

    $data = $filmTiedApi->search('Godfather', 1, 3, 'tv-series');
    var_dump($data);
    echo '<hr>';

?>
