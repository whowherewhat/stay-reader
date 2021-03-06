<?php
/**
 * User: Chester
 */

namespace App\Crawler;


use Illuminate\Support\Facades\DB;

class StayReader
{
    protected $txtArticleUrl = 'https://www.booktxt.net/modules/article/txtarticle.php';
    protected $articleUrl = "https://www.booktxt.net/";
    protected $chapterPreg = '/^(第(一|二|三|四|五|六|七|八|九|十|百|千|零|\d){1,6}(章|集|节|篇)\s{1,3}.*)/';

    protected $downloader = null;
    protected $chapters = [];

    /**
     * StayReader constructor.
     */
    public function __construct()
    {
        $this->downloader = new Downloader();
    }

    /**
     * @param string $id
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function downloadDotBookById($id = '')
    {
        $summary = $this->downloader->downloadBookSummary($this->articleUrl, $id);
        if (!$summary) {
            abort('404', '图书不存在');
        }
        $book = $this->insertBookSummary($summary);
        if ($book === false) {
            abort('500', '图书插入失败');
        }
        $fileName = $this->downloader->downloadTextFile($this->txtArticleUrl, ['id' => $id]);
        $this->parserSmart($fileName, $id);

        $this->finishDownloadBook($id);

    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function downloadDotAllBooks()
    {
        $bookArray = $this->downloader->downALLNovelsIDs();
        foreach ($bookArray as $book_id) {
            echo "Download Novel {$book_id}" . PHP_EOL;
            $this->downloadDotBookById($book_id);
        }
    }

    protected function parserBySpilt($fileName, $book_id)
    {
        if (file_exists($fileName)) {
            $handle = fopen($fileName, 'r');
            $spaceNum = 0;
            $firstLine = true;
            $firstChapter = true;

            $chapterName = '';
            $chapterContents = '';
            $this->clearBookChapters($book_id);
            while (true) {
                if (feof($handle)) {
                    $chapter['book_id'] = $book_id;
                    $chapter['chapter'] = $chapterName;
                    $chapter['contents'] = $chapterContents;
                    $this->insertBookChapter($chapter);
                    break;
                }
                $lineContent = fgets($handle);
                if (trim($lineContent) == '' || $firstLine) {
                    $spaceNum++;
                } else {
                    if ($spaceNum == 3) {
                        $chapter['book_id'] = $book_id;
                        $chapter['chapter'] = $chapterName;
                        $chapter['contents'] = $chapterContents;
                        $firstChapter || $this->insertBookChapter($chapter);
                        $firstChapter = false;
                        $chapterName = $lineContent;
                    } else if ($spaceNum == 4) {
                        $chapterContents = '';
                    } else {
                        $chapterContents = $chapterContents . "{$lineContent}";
                    }
                    $spaceNum = 0;
                }
                $firstLine = false;
            }
            fclose($handle);
        }
    }

    protected function parserSmart($fileName, $book_id)
    {
        if (file_exists($fileName)) {
            $handle = fopen($fileName, 'r');
            $chapterName = '';
            $chapterContents = '';
            $this->clearBookChapters($book_id);
            while (true) {
                if (feof($handle)) {
                    if ($chapterContents != '' && $chapterName != '') {
                        $chapter['book_id'] = $book_id;
                        $chapter['chapter'] = $chapterName;
                        $chapter['contents'] = $chapterContents;
                        $this->insertBookChapter($chapter);
                    }
                    break;
                }
                $lineContent = fgets($handle);
                $lineContent = trim($lineContent);
                if (preg_match($this->chapterPreg, $lineContent, $matches)) {
                    if ($chapterContents != '' && $chapterName != '') {
                        $chapter['book_id'] = $book_id;
                        $chapter['chapter'] = $chapterName;
                        $chapter['contents'] = $chapterContents;
                        $this->insertBookChapter($chapter);
                        $chapterContents = '';
                    }
                    $chapterName = $matches[0];
                } else {
                    if ($lineContent) {
                        $chapterContents .= $lineContent;
                    }
                }
            }
            fclose($handle);
        }
    }

    /**
     * @param $chapter
     * @return mixed
     */
    protected function insertBookChapter($chapter)
    {
        $chapter['created_date'] = date("Y-m-d H:i:s");
        $chapter['modified_date'] = date("Y-m-d H:i:s");
        try {
            DB::table('sr_book_contents')->insert($chapter);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    protected function finishDownloadBook($id)
    {
        if (DB::table('sr_book_contents')->where('book_id', $id)->first()) {
            $chapter['is_download'] = 1;
            DB::table('sr_book')->where('book_id', $id)->update($chapter);
        }
    }

    protected function clearBookChapters($id)
    {
        return DB::table('sr_book_contents')->where('book_id', $id)->delete();
    }

    /**
     * @param $book
     * @return mixed
     */
    protected function insertBookSummary($book)
    {
        if (DB::table('sr_book')->where('book_id', $book['book_id'])->first()) {
            return true;
        }
        return DB::table('sr_book')->insert($book);
    }
}