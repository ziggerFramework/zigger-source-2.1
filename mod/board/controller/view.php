<?php
namespace Module\Board;

use Corelib\Method;
use Corelib\Func;
use Corelib\Session;
use Corelib\Valid;
use Make\Database\Pdosql;
use Module\Board\Library as Board_Library;

class View extends \Controller\Make_Controller {

    static private $show_pwdform = 0;
    static public $boardconf;

    public function init()
    {
        $this->layout()->view('');

        if (View::$show_pwdform == 0) {
            $this->layout()->view(MOD_BOARD_THEME_PATH.'/board/'.View::$boardconf['theme'].'/view.tpl.php');
        } else {
            $this->layout()->view(MOD_BOARD_THEME_PATH.'/board/'.View::$boardconf['theme'].'/password.tpl.php');
        }
    }

    public function func()
    {
        //비밀글 아이콘 출력
        function secret_ico($arr)
        {
            if ($arr['use_secret'] == 'Y') {
                return '<img src=\''.MOD_BOARD_THEME_DIR.'/images/secret-ico.png\' align=\'absmiddle\' title=\'비밀글\' alt=\'비밀글\' />';
            }
        }

        //삭제 버튼
        function delete_btn($arr)
        {
            global $MB;

            if ($MB['level'] <= View::$boardconf['ctr_level'] && !$arr['dregdate']) {
                $is_btn_show = true;

            } else if ($arr['mb_idx'] == '0' && !IS_MEMBER && $MB['level'] <= View::$boardconf['delete_level'] && !$arr['dregdate']) {
                $is_btn_show = true;

            } else if ($arr['mb_idx'] == $MB['idx'] && $MB['level'] <= View::$boardconf['delete_level'] && !$arr['dregdate']) {
                $is_btn_show = true;

            } else {
                $is_btn_show = false;
            }

            if ($is_btn_show) {
                return '<button type=\'button\' class=\'btn2\' id=\'del-btn\'><i class=\'fa fa-trash-alt\'></i> 삭제</button>';
            }
        }

        //수정 버튼
        function modify_btn($arr, $read, $page, $keyword, $where, $category)
        {
            global $MB;

            if ($MB['level'] <= View::$boardconf['ctr_level'] && !$arr['dregdate']) {
                $is_btn_show = true;

            } else if ($arr['mb_idx'] == '0' && !IS_MEMBER && View::$boardconf['write_level'] == 10 && !$arr['dregdate']) {
                $is_btn_show = true;

            } else if ($arr['mb_idx'] == $MB['idx'] && $MB['level'] <= View::$boardconf['write_level'] && !$arr['dregdate']) {
                $is_btn_show = true;

            } else {
                $is_btn_show = false;
            }

            if ($is_btn_show) {
                return '<a href=\'?mode=write&wrmode=modify&category='.urlencode($category).'&read='.$read.'&page='.$page.'&where='.$where.'&keyword='.urlencode($keyword).'\' class=\'btn1\'>수정</a>';
            }
        }

        //답글 버튼
        function reply_btn($arr, $read, $page, $keyword, $where, $category)
        {
            global $MB;

            if (($MB['level'] > View::$boardconf['write_level'] && $MB['level'] > View::$boardconf['ctr_level']) || $arr['use_notice'] == 'Y' || View::$boardconf['use_reply'] == 'N' || $MB['level'] > View::$boardconf['reply_level'] || $arr['dregdate'] != '') {
                $is_btn_show = false;

            } else {
                $is_btn_show = true;
            }

            if ($is_btn_show) {
                return '<a href="?mode=write&wrmode=reply&category='.urlencode($category).'&read='.$read.'&page='.$page.'&where='.$where.'&keyword='.urlencode($keyword).'" class="btn1"><i class="fa fa-pen"></i> 답글</a>';
            }
        }

        //리스트 버튼
        function list_btn($page, $keyword, $where, $category)
        {
            return '<a href="?category='.urlencode($category).'&page='.$page.'&where='.$where.'&keyword='.urlencode($keyword).'" class="btn1"><i class="fa fa-bars"></i> 리스트</a>';
        }

        //첨부 이미지 출력
        function print_imgfile($arr)
        {
            $files = array();
            for ($i = 1; $i <= 2; $i++) {
                $file_type = Func::get_filetype($arr['file'.$i]);

                if (Func::chkintd('match', $file_type,SET_IMGTYPE)) {
                    $files[$i] = '<img src=\''.PH_DOMAIN.MOD_BOARD_DATA_DIR.'/'.View::$boardconf['id'].'/thumb/'.$arr['file'.$i].'\' alt=\'첨부된 이미지파일\' />';

                } else {
                    $files[$i] = null;
                }
            }
            return $files;
        }

        //첨부파일명 및 용량(Byte) 출력
        function print_file_name($arr)
        {
            $files = array();

            for ($i = 1; $i <= 2; $i++) {
                if ($arr['file'.$i]) {
                    $files[$i] = '
                    <a href=\''.MOD_BOARD_DIR.'/controller/file/down?board_id='.View::$boardconf['id'].'&file='.urlencode($arr['file'.$i]).'&OUTLOAD=1\' target=\'_blank\'>'.Func::strcut($arr['file'.$i],0,70).'</a>
                    <span class=\'byte\'>('.Func::filesize(MOD_BOARD_DATA_PATH.'/'.View::$boardconf['id'].'/'.$arr['file'.$i],'K').'K)</span>
                    <span class=\'cnt\'><strong>'.Func::number($arr['file'.$i.'_cnt']).'</strong> 회 다운로드</span>
                    ';

                } else {
                    $files[$i] = null;
                }
            }
            return $files;
        }

        //회원 이름
        function print_writer($arr)
        {
            if ($arr['mb_idx'] != 0) {
                return '<a href="#" data-profile="'.$arr['mb_idx'].'">'.$arr['writer'].'</a>';

            } else {
                return $arr['writer'];
            }
        }
    }

    public function make()
    {
        global $MB, $MOD_CONF, $board_id;

        $sql = new Pdosql();
        $sess = new Session();
        $boardlib = new Board_Library();

        $board_id = $MOD_CONF['id'];
        View::$boardconf = $boardlib->load_conf($board_id);

        $req = Method::request('get', 'mode, wrmode, read, page, where, keyword, category');

        if (isset($_POST['s_password'])) {
            $s_req = Method::request('post', 's_password');
        }

        $board_id = $MOD_CONF['id'];

        //패스워드가 submit 된 경우
        if (isset($s_req['s_password'])) {
            $s_req = Method::request('post', 's_password, s_read, s_page, s_category, s_where, s_keyword');
            $req['read'] = $s_req['s_read'];
            $req['page'] = $s_req['s_page'];
            $req['category'] = $s_req['s_category'];
            $req['where'] = $s_req['s_where'];
            $req['keyword'] = $s_req['s_keyword'];
        }

        //add stylesheet & javascript
        $boardlib->print_headsrc(View::$boardconf['theme']);

        //load session
        $view_sess = $sess->sess('BOARD_VIEW_'.$req['read']);

        //원본 글 불러옴
        $sql->query(
            "
            SELECT
            (
            SELECT COUNT(*)
            FROM {$sql->table("mod:board_like")}
            WHERE id='$board_id' AND data_idx=:col1 AND likes>0
            ) likes_cnt,
            (
                SELECT COUNT(*)
                FROM {$sql->table("mod:board_like")}
                WHERE id='$board_id' AND data_idx=:col1 AND unlikes>0
            ) unlikes_cnt,
            board.*
            FROM {$sql->table("mod:board_data_".$board_id)} board
            WHERE board.idx=:col1
            ",
            array(
                $req['read']
            )
        );

        if ($sql->getcount() < 1) {
            Func::err_back('해당 글이 존재하지 않습니다.');
        }

        $arr = $sql->fetchs();

        $sql->specialchars = 0;
        $sql->nl2br = 0;

        $arr['article'] = $sql->fetch('article');

        //captcha 시작 (코멘트에 captcha 노출하기 위해 view 에서 미리 js로드)
        Func::get_captcha('captcha', 0);
        Func::get_captcha('re_captcha', 0);

        //add title
        Func::add_title(View::$boardconf['title'].' - '.$arr['subject']);

        //게시물이 답글이며 회원에 대한 답글인 경우 부모글의 회원 idx 가져옴
        if ($arr['rn'] > 0 && $arr['pwd'] == '') {
            $sql->query(
                "
                SELECT *
                FROM {$sql->table("mod:board_data_".$board_id)}
                WHERE ln>:col1 AND rn=:col2
                ORDER BY ln ASC
                LIMIT 1
                ",
                array(
                    $arr['ln'],
                    $arr['rn'] - 1
                )
            );
            $prt_mb_idx = $sql->fetch('mb_idx');
        }

        //패스워드가 submit된 경우(비밀글) 패스워드가 일치 하는지 검사
        if (isset($s_req['s_password'])) {
            if ($arr['pwd'] == $s_req['s_password']) {
                $rd_level = 1;

            } else {
                $rd_level = 3;
                Func::err_back('비밀번호가 일치하지 않습니다.');
            }
        }

        //패스워드 submit이 아닌 경우, 글 읽기 권한이 있는지 검사
        if (!isset($s_req['s_password'])) {

            //비밀글인 경우
            if ($arr['use_secret'] == 'Y') {

                //관리자 레벨 이거나, 비밀글 읽기 권한이 있는 경우 글을 보임
                if ($MB['level'] <= View::$boardconf['ctr_level'] || $MB['level'] <= View::$boardconf['secret_level']) {
                    $rd_level = 1;

                }

                //그 외
                else {

                    //비회원의 글이고 로그인 되지 않은 경우 패스워드 폼을 보임
                    if ($arr['mb_idx'] == 0 && !IS_MEMBER) {
                        $rd_level = 3;


                    }

                    //글이 답글이고, 비밀번호가 저장되어 있는 경우(비회원 글에 대한 답변) 패스워드 폼을 보임
                    else if ($arr['rn'] > 0 && $arr['pwd'] != '' && !IS_MEMBER) {
                        $rd_level = 3;

                    }

                    //글이 답글이고, 자신의 글에 대한 답글인 경우 글을 보임
                    else if ($arr['rn'] > 0 && $prt_mb_idx == $MB['idx']) {
                        $rd_level = 1;

                    }

                    //자신의 글인 경우 글을 보임else if($arr['mb_idx']==$MB['idx']){
                    else if ($arr['mb_idx'] == $MB['idx']) {
                        $rd_level = 1;

                    }

                    //그 외 아무 권한 없음
                    else {
                        $rd_level = 0;
                    }
                }


            }

            //비밀글이 아닌 경우
            else if ($arr['use_secret'] == 'N') {

                //글 읽기 권한이 있는 경우 글을 보임
                if ($MB['level'] <= View::$boardconf['read_level']) {
                    $rd_level = 1;

                }

                //그 외
                else {

                    //공지글인 경우 글을 보임
                    if ($arr['use_notice'] == 'Y') {
                        $rd_level = 1;

                    }

                    //로그인 되어있지 않은 경우 패스워드 폼을 보임
                    else if ($arr['mb_idx'] == 0 && !IS_MEMBER) {
                        $rd_level = 3;

                    }

                    //그 외 아무 권한 없음
                    else {
                        $rd_level = 0;
                    }
                }

            }
        }

        //글 조회 포인트 조정
        if (View::$boardconf['read_point'] < 0) {
            if (!IS_MEMBER) {
                Func::err_back('포인트 설정으로 인해 비회원은 글을 조회할 수 없습니다.');
            }
            if (IS_MEMBER && !isset($view_sess) && $arr['mb_idx'] != $MB['idx']) {
                if ($MB['point'] < (0 - View::$boardconf['read_point'])) {
                    Func::err_back('포인트가 부족하여 글을 조회할 수 없습니다.');
                }

                $point = 0 - View::$boardconf['read_point'];

                Func::set_mbpoint(
                    array(
                        'mb_idx' => $MB['idx'],
                        'mode' => 'out',
                        'point' => $point,
                        'msg' => '게시판 글 조회 ('.View::$boardconf['title'].')'
                    )
                );
            }

        } else if (View::$boardconf['read_point'] > 0) {
            Func::set_mbpoint(
                array(
                    'mb_idx' => $MB['idx'],
                    'mode' => 'in',
                    'point' => View::$boardconf['read_point'],
                    'msg' => '게시판 글 조회 ('.View::$boardconf['title'].')'
                )
            );
        }

        //조회수 증가
        if (!isset($view_sess)) {
            $sql->query(
                "
                UPDATE {$sql->table("mod:board_data_".$board_id)}
                SET view = view + 1
                WHERE idx=:col1
                ",
                array(
                    $req['read']
                )
            );
            $sess->set_sess('BOARD_VIEW_'.$req['read'], $req['read']);
        }

        //패스워드 입력폼 노출
        if ($rd_level == 3) {

            View::$show_pwdform = 1;
        }

        //보기 권한이 없는 경우
        if ($rd_level == 0) {

            switch ($arr['use_secret']) {

                case 'N' :
                    Func::getlogin(SET_NOAUTH_MSG);
                    break;

                case 'Y' :
                    Func::err_back('접근 권한이 없습니다.');
                    break;
            }

        }

        //view 노출
        if ($rd_level == 1) {

            View::$show_pwdform = 0;

            if ($arr['dregdate']) {
                $is_dropbox_show = true;
                $is_article_show = false;

            } else {
                $is_dropbox_show = false;
                $is_article_show = true;
            }

            $is_file_show = array();

            for ($i = 1; $i <= 2; $i++) {
                if ($arr['file'.$i]) {
                    $is_file_show[$i] = true;

                } else {
                    $is_file_show[$i] = false;
                }
            }

            $is_img_show = array();

            for ($i = 1; $i <= 2; $i++){
                if (print_imgfile($i, $arr) != '') {
                    $is_img_show[$i] = true;
                } else {
                    $is_img_show[$i] = false;
                }
            }

            if (View::$boardconf['use_category'] == 'Y' && $arr['category'] && $arr['use_notice'] == 'N') {
                $is_category_show = true;

            } else {
                $is_category_show = false;
            }

            if (View::$boardconf['use_comment'] == 'Y') {
                $is_comment_show = true;
            } else {
                $is_comment_show = false;
            }

            if (View::$boardconf['use_likes'] == 'Y' && !$arr['dregdate']) {
                $is_likes_show = true;
            } else {
                $is_likes_show = false;
            }

            if (View::$boardconf['use_list'] == 'Y') {
                $is_ftlist_show = true;
            } else {
                $is_ftlist_show = false;
            }

            $arr['view'] = Func::number($arr['view']);
            $arr['date'] = Func::date($arr['regdate']);
            $arr['datetime'] = Func::datetime($arr['regdate']);
            $arr['likes_cnt'] = Func::number($arr['likes_cnt']);
            $arr['unlikes_cnt'] = Func::number($arr['unlikes_cnt']);

            $view = array();

            if (isset($arr)) {
                foreach ($arr as $key => $value) {
                    $view[$key] = $value;
                }
            } else {
                $view = null;
            }

            $this->set('view', $view);
            $this->set('is_dropbox_show', $is_dropbox_show);
            $this->set('is_article_show', $is_article_show);
            $this->set('is_file_show', $is_file_show);
            $this->set('is_img_show', $is_img_show);
            $this->set('is_category_show', $is_category_show);
            $this->set('is_comment_show', $is_comment_show);
            $this->set('is_likes_show', $is_likes_show);
            $this->set('is_ftlist_show', $is_ftlist_show);
            $this->set('secret_ico', secret_ico($arr));
            $this->set('print_writer', print_writer($arr));
            $this->set('print_imgfile', print_imgfile($arr));
            $this->set('print_file_name', print_file_name($arr));
            $this->set('list_btn', list_btn($req['page'], $req['keyword'], $req['where'], $req['category']));
            $this->set('delete_btn', delete_btn($arr));
            $this->set('modify_btn', modify_btn($arr,$req['read'], $req['page'], $req['keyword'], $req['where'], $req['category']));
            $this->set('reply_btn', reply_btn($arr, $req['read'], $req['page'], $req['keyword'], $req['where'], $req['category']));

        }

        $this->set('mode', $req['mode']);
        $this->set('wrmode', $req['wrmode']);
        $this->set('board_id', $board_id);
        $this->set('category', $req['category']);
        $this->set('read', $req['read']);
        $this->set('page', $req['page']);
        $this->set('where', $req['where']);
        $this->set('keyword', $req['keyword']);
        $this->set('thisuri', Func::thisuri());
        $this->set('top_source', View::$boardconf['top_source']);
        $this->set('bottom_source', View::$boardconf['bottom_source']);
    }

    public function pass_form()
    {
        $form = new \Controller\Make_View_Form();
        $form->set('id', 'board-pwdForm');
        $form->set('type', 'static');
        $form->set('target', 'view');
        $form->set('method', 'post');
        $form->run();
    }

    public function likes_form()
    {
        $form = new \Controller\Make_View_Form();
        $form->set('id', 'board-likes');
        $form->set('type', 'html');
        $form->set('action', MOD_BOARD_DIR.'/controller/view/get-likes');
        $form->run();
    }

}

/***
Submit for Get likes
***/
class Get_likes {

    public function init()
    {
        global $MB;

        $sql = new Pdosql();
        $boardlib = new Board_Library();

        Method::security('referer');
        Method::security('request_post');
        $req = Method::request('post','board_id, read, mode');

        //load config
        View::$boardconf = $boardlib->load_conf($req['board_id']);

        //check
        if (View::$boardconf['use_likes'] == 'N') {
            Valid::error('', '추천 기능이 비활성화 되어 있습니다.');
        }

        if (!IS_MEMBER) {
            Valid::error('', '추천 권한이 없습니다. 추천 기능은 회원만 이용 가능합니다.');
        }

        //이미 참여 하였는지 검사
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_like")}
            WHERE id=:col1 AND data_idx=:col2 AND mb_idx=:col3
            ",
            array(
                $req['board_id'], $req['read'], $MB['idx']
            )
        );

        if ($sql->getcount() > 0) {
            Valid::error('', '이미 참여 하였습니다.');
        }

        //like
        if ( $req['mode'] == 'likes') {

            $sql->query(
                "
                INSERT INTO {$sql->table("mod:board_like")}
                (id,data_idx,mb_idx,likes,unlikes,regdate)
                VALUES
                (:col1,:col2,:col3,1,0,now())
                ",
                array(
                    $req['board_id'], $req['read'], $MB['idx']
                )
            );

            $sql->query(
                "
                SELECT
                COUNT(*) total_cnt
                FROM {$sql->table("mod:board_like")}
                WHERE id=:col1 AND data_idx=:col2 AND likes>0
                ",
                array(
                    $req['board_id'], $req['read']
                )
            );
            $return_ele = '#board-likes-cnt';

        //unlike
        } else {

            $sql->query(
                "
                INSERT INTO {$sql->table("mod:board_like")}
                (id,data_idx,mb_idx,likes,unlikes,regdate)
                VALUES
                (:col1,:col2,:col3,0,1,now())
                ",
                array(
                    $req['board_id'], $req['read'], $MB['idx']
                )
            );

            $sql->query(
                "
                SELECT
                COUNT(*) total_cnt
                FROM {$sql->table("mod:board_like")}
                WHERE id=:col1 AND data_idx=:col2 AND unlikes>0
                ",
                array(
                    $req['board_id'], $req['read']
                )
            );
            $return_ele = '#board-unlikes-cnt';

        }

        Valid::set(
            array(
                'return' => 'callback-txt',
                'element' => $return_ele,
                'msg' => $sql->fetch('total_cnt')
            )
        );
        Valid::turn();
    }

}
