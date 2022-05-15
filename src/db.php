<?php

class db_sync extends WPD_Douban
{

    private $base_url = 'https://fatesinger.com/dbapi/';

    public function __construct()
    {
        $this->uid = db_get_setting('id');
        add_action('db_sync', [$this, 'db_sync_data']);
    }

    public function db_fecth($start = 0, $type = 'movie')
    {
        $url = "{$this->base_url}user/{$this->uid}/interests?count=49&start={$start}&type={$type}";
        $response = wp_remote_get($url);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $interests = $data['interests'];

        return $interests;
    }

    public function add_log($type = 'movie')
    {
        global $wpdb;
        $wpdb->insert($wpdb->douban_log, [
            'type' => $type,
            'action' => 'sync',
            'create_time' => date('Y-m-d H:i:s'),
            'status' => 'success',
            'message' => 'sync success',
            'account_id' => $this->uid
        ]);
    }

    public function db_sync_data()
    {
        if (!$this->uid) {
            return;
        }
        $sync_types = [
            'movie',
            'music',
            'book',
            'game',
            'drama'
        ];
        global $wpdb;

        //$this->get_collections('ECHMOGZLQ');
        foreach ($sync_types as $type) {
            $confition = true;
            $i = 0;
            while ($confition) {
                $data = $this->db_fecth(49 * $i, $type);
                if (empty($data)) {
                    $confition = false;
                } else {
                    foreach ($data as $interest) {
                        $movie = $wpdb->get_results("SELECT * FROM $wpdb->douban_movies WHERE `type` = '" . $type . "' AND douban_id = '{$interest['subject']['id']}'");
                        if (empty($movie)) {
                            $wpdb->insert(
                                $wpdb->douban_movies,
                                array(
                                    'name' => $interest['subject']['title'],
                                    'poster' => $interest['subject']['pic']['large'],
                                    'douban_id' => $interest['subject']['id'],
                                    'douban_score' => $interest['subject']['rating']['value'],
                                    'link' => $interest['subject']['url'],
                                    'year' => $interest['subject']['year'],
                                    'type' => $type,
                                    'pubdate' => $interest['subject']['pubdate'] ? $interest['subject']['pubdate'][0] : '',
                                    'card_subtitle' => $interest['subject']['card_subtitle'],
                                )
                            );
                            if ($wpdb->insert_id) {
                                $movie_id = $wpdb->insert_id;
                                foreach ($interest['subject']['genres'] as $genre) {
                                    $wpdb->insert(
                                        $wpdb->douban_genres,
                                        [
                                            'movie_id' => $movie_id,
                                            'name' => $genre,
                                            'type' => $type,
                                        ]
                                    );
                                }
                                $wpdb->insert(
                                    $wpdb->douban_faves,
                                    [
                                        'create_time' => $interest['create_time'],
                                        'remark' => $interest['comment'],
                                        'score' => $interest['rating'] ? $interest['rating']['value'] : '',
                                        'subject_id' => $movie_id,
                                        'type' => $type,
                                        'status' => 'done'
                                    ]
                                );
                            }
                        } else {
                            $movie_id = $movie[0]->id;
                            $fav = $wpdb->get_results("SELECT * FROM $wpdb->douban_faves WHERE `type` = '" . $type . "'  AND subject_id = '{$movie_id}'");
                            if (empty($fav)) {
                                $wpdb->insert(
                                    $wpdb->douban_faves,
                                    [
                                        'create_time' => $interest['create_time'],
                                        'remark' => $interest['comment'],
                                        'score' => $interest['rating'] ? $interest['rating']['value'] : '',
                                        'subject_id' => $movie_id,
                                        'status' => 'done',
                                        'type' => $type,
                                    ]
                                );
                            } else {
                                $confition = false;
                            }
                        }
                    }
                    $i++;
                }
            }
            $this->add_log($type);
        }
    }
}

new db_sync();
