<?php
    namespace Page;

    class Manga
    {
        private $pageLimit = 10;

        private function display($title, $sort, $search=false)
        {
            $this->load->model('Manga');
            $this->load->library('Manga', 'MangaLib');
            $this->load->library('Date');
            $this->load->library('Image');
            $this->load->helper('Paging');

            $this->config->setDefaultInfo('Manga', ['directoryMangaMax'=>36]);
            $cfg = $this->config->loadInfo('Manga');
            $count = $this->manga->getCount();
            $maxManga = $cfg['directoryMangaMax'];

            $curPage = 1;
            if (($page = $this->uri->pair('page')) !== false)
            {
                $curPage = $page;
            }

            $result = null;
            if ($search === false)
            {
                $result = $this->manga->getList($curPage-1, $sort, $maxManga);
            }
            else
            {
                $result = $this->manga->findManga($search, $curPage-1);
                $count = $this->manga->getSearchCount($search);
            }

            $maxPage = $count / $maxManga;
            $this->load->storeView('MangaDirectory', [
                'mangalist'=>$result,
                'mangapath'=>$cfg['path'],
                'page'=>paging($curPage, $maxPage),
                'mangaCount'=>$count,
                'curpage'=>$curPage
            ]);

            $this->load->layout('Fresh', [
                'title'=>$title,
                'additionalJs'=>[
                    'cardsorter'
                ]
            ]);
        }

        public function directory()
        {
            $this->display('Manga Directory', 'friendly_name');
        }

        public function hot()
        {
            $this->display('Hot Manga', 'views');
        }

        public function latest()
        {
            $this->display('Latest Updated', 'update_at');
        }

        public function search()
        {
            if (!$this->input->hasGet())
            {
                $this->router->redirect('manga/directory');
            }

            $this->display('Search Result', '', $this->input->get('search'));
        }

        private function chapter()
        {
            $this->load->model('Manga');
            $this->load->library('Manga', 'MangaLib');
            $this->load->library('Date');

            $manga = $this->manga->getMangaF($this->uri->segment(2));
            $result = $this->manga->getChapters($manga->id);

            $order = array();
            $chapters = array();
            while ($row = $result->row())
            {
                $name = $this->mangalib->nameFix($row->name, $manga->name);
                $order[] = $name;
                $chapters[$name] = $row;
            }

            natsort($order);
            $order = array_reverse($order);

            $history = $this->manga->getMangaHistory(
                $this->auth->getUserId(), $manga->id);
            $markHistory = array();
            while ($row = $history->row())
            {
                $markHistory[$row->fchapter] = true;
            }

            $history->reset();
            $this->load->storeView('MangaChapter', [
                'manga'=>$manga,
                'chapters'=>$chapters,
                'order'=>$order,
                'history'=>$history,
                'markHistory'=>$markHistory
            ]);

            $this->load->layout('Fresh', [
                'title'=>$manga->name
            ]);
        }

        private function addHistory($idManga, $idChapter, $page)
        {
            $this->load->model('Manga');
            if ($this->auth->isLoggedIn())
            {
                $idUser = $this->auth->getUserId();

                if ($this->manga->addHistory($idUser, $idManga, $idChapter, $page))
                {
                    $this->manga->addReadCount($idManga);
                }
            }
        }

        private function read($fchapter)
        {
            $this->load->model('Manga');
            $this->load->library('Manga', 'MangaLib');
            $this->load->library('Image');

            $this->config->setDefaultInfo('Manga', ['readMaxImage'=>10]);

            $cfg = $this->config->loadInfo('Manga');
            $this->pageLimit = $cfg['readMaxImage'];
            $fmanga = $this->uri->segment(2);
            $manga = $this->manga->getMangaF($fmanga);

            $res = $this->manga->getChapters($manga->id);
            $chapters = array();
            while ($row = $res->row())
            {
                $fixName = $this->mangalib->nameFix($row->name, $manga->name);
                $order[] = $fixName;
                $chapters[$this->mangalib->toFriendlyName($fixName)] =
                    $row;
            }

            natsort($order);
            $order = array_values($order);
            $count = count($order);

            // Get current index
            $curI = -1;
            for ($i = 0; $i < $count; $i++)
            {
                $order[$i] = $this->mangalib->toFriendlyName($order[$i]);
                if (strcmp($order[$i], $fchapter) === 0)
                {
                    $curI = $i;
                }
            }
            $curFChapter = $order[$curI];

            // Get start page
            $page = 0;
            if (($pair = $this->uri->pair('page')) !== false)
            {
                $page = $pair-1;
            }

            $chapter = $this->manga->getChapterF($manga->id, $order[$curI]);
            $prevChapter = $chapter;
            $nextChapter = $chapter;

            $this->addHistory($manga->id, $chapter->id, $page+1);

            // Generate Prev Link
            $pI = $curI;
            $pImageCount = $page;
            $prevLink = "manga/$fmanga";

            if ($pImageCount >= $this->pageLimit)
            {
                $prevLink = "manga/$fmanga/chapter/$chapter->friendly_name";
                if ($pImageCount > 0)
                {
                    $prevLink .= "/page/".(($pImageCount-$this->pageLimit)+1);
                }
            }
            else
            {
                while ($pI > 0 && $pImageCount < $this->pageLimit)
                {
                    $pI--;
                    $prevChapter = $this->manga->getChapterF($manga->id,
                        $this->mangalib->toFriendlyName($order[$pI]));
                    $maxImage = $this->manga->getImageCount($prevChapter->id_manga,
                        $prevChapter->id);

                    $need = $this->pageLimit - $pImageCount;

                    if ($maxImage >= $need)
                    {
                        $prevLink = "manga/$fmanga/chapter/$prevChapter->friendly_name";
                        $prevLink .= "/page/".(($maxImage-$need)+1);
                    }

                    $pImageCount += $maxImage;
                }
            }

            $images = array();
            $imageCount = 0;
            $nextLink = "manga/$fmanga";
            while ($imageCount < $this->pageLimit && $nextChapter!==false)
            {
                $curPage = $imageCount==0 ? $page : 0;
                $result = $this->manga->getImages($nextChapter->id_manga,
                    $nextChapter->id, $curPage, $this->pageLimit-$imageCount);
                $maxImage = $this->manga->getImageCount($nextChapter->id_manga,
                    $nextChapter->id);

                while ($row = $result->row())
                {
                    $row->chapter = $nextChapter->name;
                    $row->fchapter = $nextChapter->friendly_name;
                    $images[] = $row;
                }

                $imageCount += $result->count();
                $nextLink = "manga/$fmanga/chapter/$nextChapter->friendly_name";
                if ($curPage+$result->count() < $maxImage)
                {
                    $nextLink .= "/page/".($curPage+$result->count()+1);
                }
                else
                {
                    $curI++;
                    if ($curI < $count)
                    {
                        // There is still chapters
                        $nextChapter = $this->manga->getChapterF($manga->id, 
                            $this->mangalib->toFriendlyName($order[$curI]));
                        $nextLink = "manga/$fmanga/chapter/$nextChapter->friendly_name";
                    }
                    else
                    {
                        // No more chapters
                        $nextLink = "manga/$fmanga";
                        break;
                    }
                }
            }

            $this->load->storeView('Read', [
                'manga'=>$manga,
                'chapters'=>$chapters,
                'chapterOrder'=>$order,
                'chapterCurrent'=>$curFChapter,
                'path'=>$cfg['path'],
                'images'=>$images,
                'prevLink'=>$prevLink,
                'nextLink'=>$nextLink,
                'count'=>$imageCount
            ]);

            $this->load->layout('Fresh', [
                'simpleMode'=>true,
                'readMode'=>true,
                'additionalJs'=>['read'],
                'title'=>$this->mangalib->nameFix($chapter->name, $manga->name)
            ]);
        }

        private function mark($mark)
        {
            $this->load->model('Manga');
            $manga = $this->manga->getMangaF($this->uri->segment(2));

            if ($this->auth->getUserOption('privilege') === 'admin')
            {
                if (strcasecmp($mark, 'completed') === 0)
                {
                    $this->manga->setOption($manga->id, 'status', 'completed');
                }
                elseif (strcasecmp($mark, 'ongoing') === 0)
                {
                    $this->manga->setOption($manga->id, 'status', 'ongoing');
                }
            }

            $this->router->redirect('manga/'.$this->uri->segment(2));
        }

        public function route()
        {
            if (($chapter = $this->uri->pair('chapter')) !== false)
            {
                $this->read($chapter);
            }
            elseif (($mark = $this->uri->pair('mark')) !== false)
            {
                $this->mark($mark);
            }
            else
            {
                $this->chapter();
            }
        }
    }
?>
