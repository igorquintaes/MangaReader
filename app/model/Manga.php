<?php
    namespace Model;

    class Manga
    {
        public function getList($page=0)
        {
            return $this->db->table('manga')
                ->join('manga_chapter','manga.id', 'manga_chapter.id_manga')
                ->order('manga.friendly_name')->limit($page, 36)
                ->group('manga.id')->get("manga.*, count(manga.id) as cnt");
        }

        public function getCount()
        {
            return $this->db->table('manga')
                ->get("count(manga.id) as cnt")->first()->cnt;
        }

        public function getImage($id)
        {
            return $this->db->table('manga_image')
                ->join('manga','manga.id', 'manga_image.id_manga')
                ->join('manga_chapter','manga_chapter.id', 'manga_image.id_chapter')
                ->where('manga.id', 'manga_chapter.id_manga')
                ->where('manga.id', $id)
                ->where('manga_image.page', '1')
                ->order('manga_image.id')->limit(0, 1)->group('manga_image.id')
                ->get("manga_image.name, manga.name as manga_name, manga_chapter.name as chapter_name");
        }

        public function addReadCount($id)
        {
            $this->db->table('manga')->where('id', $id)
                ->update('`views`=`views`+1');
            $current = $this->db->table('manga')->where('id', $id)
                ->get()->first();

            $above = $this->db->table('manga')->order('rankings', false)
                ->limit(0,1)->where('rankings','<', $current->rankings)->get();

            if ($above->isEmpty())
            {
                $this->db->table('manga')->where('id', $id)
                    ->update(['rankings'=>'1']);
                $this->db->table('manga')->where('id', '!=', $id)
                    ->where('rankings', '>=', 1)
                    ->update('`rankings`=`rankings`+1');
            }
            elseif ($current->rankings === '0')
            {
                // Find placement
                $this->db->table('manga')->where('id', $id)
                    ->update(['rankings'=>$above->first()->rankings+1]);
            }
            else
            {
                $this->db->table('manga')->where('id', $id)
                    ->update(['rankings'=>$above->first()->rankings+1]);
                $this->db->table('manga')->where('id', '!=', $id)
                    ->where('rankings', '>=', $above->first()->rankings+1)
                    ->where('rankings', '<', $current->rankings)
                    ->update('`rankings`=`rankings`+1');
            }
        }
    }

?>