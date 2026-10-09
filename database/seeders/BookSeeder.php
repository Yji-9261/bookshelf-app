<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Genre;

class BookSeeder extends Seeder
{
    protected $initialValues = [
        [
            'title' => '吾輩は猫である',
            'author' => '夏目漱石',
            'isbn' => '9784101010014',
            'published_date' => '1905-01-01',
            'ジャンル' => ['小説'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=1',
            'description' => '夏目漱石の長編小説であり、処女小説である。'
        ],

        [
            'title' => '人を動かす',
            'author' => 'D・カーネギー',
            'isbn' => '9784422100524',
            'published_date' => '1936-10-01',
            'ジャンル' => ['ビジネス', '自己啓発'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=2',
            'description' => 'デール・カーネギーの代表的な著書。自己啓発書の元祖と称されることも多い。'
        ],

        [
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'ジャンル' => ['技術書'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=3',
            'description' => '「美しいコードを見ると感動する。優れたコードは見た瞬間に何をしているかが伝わってくる。そういうコードは使うのが楽しいし、
自分のコードもそうあるべきだと思わせてくれる。本書の目的は、君のコードを良くすることだ」。',
        ],

        [
            'title' => '7つの習慣',
            'author' => 'スティーブン・R・コヴィー',
            'isbn' => '9784863940246',
            'published_date' => '2013-08-30',
            'ジャンル' => ['ビジネス', '自己啓発'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=4',
            'description' => 'ジャンルはビジネス書とされる場合が多いが、成功哲学、人生哲学、自助努力といった人間の生活を広く取り扱っており、人文・思想、倫理・道徳、人生論・教訓、自己啓発などに分類される場合もある。'
        ],

        [
            'title' => '坊っちゃん',
            'author' => '夏目漱石',
            'isbn' => '9784101010021',
            'published_date' => '1906-04-01',
            'ジャンル' => ['小説'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=5',
            'description' => '登場する人物の描写が滑稽で、わんぱく坊主のいたずらあり、悪口雑言あり、暴力沙汰あり、痴情のもつれあり、義理人情ありと、他の漱石作品と比べて大衆的であり、難解なものが多い漱石作品の中では比較的平易であり、最も多くの人に愛読されている作品と評価されている。'
        ],

        [
            'title' => 'サピエンス全史',
            'author' => 'ユヴァル・ノア・ハラリ',
            'isbn' => '9784309226712',
            'published_date' => '2016-09-08',
            'ジャンル' => ['歴史', '科学'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=6',
            'description' => 'この書籍はホモ・サピエンスについて扱い、石器時代から21世紀までの人類の歴史を概観するものである。自然科学、特に進化生物学の観点からもそのテーマが語られる。
'
        ],

        [
            'title' => 'Clean Code',
            'author' => 'Robert C. Martin',
            'isbn' => '9784048930598',
            'published_date' => '2017-12-18',
            'ジャンル' => ['技術書'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=7',
            'description' => 'コードを書き、読み、洗練する。'
        ],

        [
            'title' => '嫌われる勇気',
            'author' => '岸見一郎・古賀史健',
            'isbn' => '9784478025819',
            'published_date' => '2013-12-13',
            'ジャンル' => ['自己啓発'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=8',
            'description' => '岸見一郎と古賀史健の共著による、アルフレッド・アドラーの「アドラー心理学」を解説した書籍。2013年12月13日にダイヤモンド社より出版された。'
        ],

        [
            'title' => '火花',
            'author' => '又吉直樹',
            'isbn' => '9784163902302',
            'published_date' => '2015-03-11',
            'ジャンル' => ['小説'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=9',
            'description' => '又吉直樹による中編小説。お笑い芸人の小説が芥川龍之介賞を取り話題となったことで、2026年には部数354万部を突破した。'
        ],

        [
            'title' => 'FACTFULNESS',
            'author' => 'ハンス・ロスリング',
            'isbn' => '9784822289607',
            'published_date' => '2019-01-11',
            'ジャンル' => ['ビジネス', '科学'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=10',
            'description' => 'ファクトフルネスとは――データや事実にもとづき、世界を読み解く習慣。賢い人ほどとらわれる10の思い込みから解放されれば、癒され、世界を正しく見るスキルが身につく。'
        ],

        [
            'title' => 'コンテナ物語',
            'author' => 'マルク・レビンソン',
            'isbn' => '9784822251468',
            'published_date' => '2007-01-18',
            'ジャンル' => ['ビジネス', '歴史'],
            'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text=11',
            'description' => '20世紀最大の発明品の１つといわれるのがコンテナ。コンテナの海上輸送が始まったのは1956年3月のことだ。'
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /**
         * books テーブルに書籍データを11件投入する。
         * 登録者は User::first()（山田太郎）とする。
         */

        foreach ($this->initialValues as $value) {
            // ジャンル名を一時保持
            $genreNames = $value['ジャンル'];

            // 不要な'ジャンル'キーを削除しbookを生成
            unset($value['ジャンル']);
            // user id=1 山田太郎固定
            $book = User::first()->registerBooks()->firstOrCreate($value);

            // ジャンル名から該当するID群を取得し、書籍にジャンル紐つけ
            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id');
            $book->genres()->sync($genreIds);
        }
    }
}
