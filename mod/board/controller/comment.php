<?php
namespace Module\Board;

use Corelib\Method;
use Corelib\Func;
use Corelib\Valid;
use Make\Library\Paging;
use Make\Database\Pdosql;
use Module\Board\Library as Board_Library;
use Module\Alarm\Library as Alarm_Library;

/***
Comment
***/
class Load extends \Controller\Make_Controller {

    public function init()
    {
        global $MOD_CONF, $boardconf;

        $req = Method::request('get', 'board_id, read');

        $board_id = $req['board_id'];
        $boardlib = new Board_Library();
        $boardconf = $boardlib->load_conf($board_id);

        $this->layout()->view(MOD_BOARD_THEME_PATH.'/board/'.$boardconf['theme'].'/comment.tpl.php');
    }

    public function func()
    {
        global $boardconf;

        //삭제 버튼
        function delete_btn($arr, $view)
        {
            global $MB, $boardconf;

            if (($arr['mb_idx'] == $MB['idx'] && $arr['mb_idx'] != 0 && !$view['dregdate']) || ($MB['level'] <= $boardconf['ctr_level'] && !$view['dregdate'])) {
                return '<a href="#" id="cmt-delete" align="absmiddle" data-cmt-delete="'.$arr['idx'].'"><img src="'.MOD_BOARD_THEME_DIR.'/images/cmt-delete-ico.png" align="absmiddle" title="삭제" alt="삭제" /> 삭제</a>';
            }
        }

        //수정 버튼
        function modify_btn($arr, $view)
        {
            global $MB, $boardconf;

            if (($arr['mb_idx'] == $MB['idx'] && $arr['mb_idx'] != 0 && !$view['dregdate']) || ($MB['level'] < $boardconf['ctr_level'] && !$view['dregdate'])) {
                return '<a href="#" id="cmt-modify" data-cmt-modify="'.$arr['idx'].'"><img src="'.MOD_BOARD_THEME_DIR.'/images/cmt-modify-ico.png" align="absmiddle" title="수정" alt="수정" /> 수정</a>';
            }
        }

        //대댓글 버튼
        function reply_btn($arr, $view)
        {
            global $MB, $boardconf;

            if ($MB['level'] <= $boardconf['comment_level'] && !$view['dregdate']) {
                return '<a href="#" id="cmt-reply" data-cmt-reply="'.$arr['idx'].'"><img src="'.MOD_BOARD_THEME_DIR.'/images/cmt-reply-ico.png" align="absmiddle" title="답변 댓글 작성" alt="답변 댓글 작성" /> 답글</a>';
            }
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

        //대댓글인 경우 들여쓰기 클래스 부여
        function reply_class($arr)
        {
            if ($arr['rn'] > 0) {
                return 'dep-'.$arr['rn'];
            }
        }
    }

    public function make()
    {
        global $MB, $boardconf, $board_id;

        $sql = new Pdosql();
        $paging = new Paging();
        $boardlib = new Board_Library();

        $req = Method::request('get','board_id, read, thisuri');

        $board_id = $req['board_id'];

        $boardconf = $boardlib->load_conf($board_id);

        //원본 글 정보 불러옴
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_data_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['read']
            )
        );
        $view = $sql->fetchs();

        if ($boardconf['use_comment'] == 'Y') {

            if (IS_MEMBER) {
                $type = 2;
            } else {
                $type = 1;
            }

            if ($MB['level'] <= $boardconf['comment_level'] && !$view['dregdate']) {
                $is_writeform_show = true;

                if (!IS_MEMBER) {
                    $is_writer_show = true;

                } else {
                    $is_writer_show = false;
                }

            } else {
                $is_writeform_show = false;
                $is_writer_show = false;
            }

            if (!IS_MEMBER) {
                $is_guest_form_show = true;

            } else {
                $is_guest_form_show = false;
            }

            //list
            $sql->query(
                "
                SELECT *
                FROM {$sql->table("mod:board_cmt_".$board_id)}
                WHERE bo_idx=:col1
                ORDER BY ln ASC, rn ASC, regdate ASC
                ",
                array(
                    $req['read']
                )
            );
            $total_cnt = Func::number($sql->getcount());
            $print_arr = array();

            if ($total_cnt > 0) {
                do {
                    $arr = $sql->fetchs();

                    $arr['date'] = Func::date($arr['regdate']);
                    $arr['datetime'] = Func::datetime($arr['regdate']);
                    $arr[0]['reply_class'] = reply_class($arr);
                    $arr[0]['reply_btn'] = reply_btn($arr,$view);
                    $arr[0]['modify_btn'] = modify_btn($arr,$view);
                    $arr[0]['delete_btn'] = delete_btn($arr,$view);
                    $arr[0]['writer'] = print_writer($arr);

                    $print_arr[] = $arr;

                } while ($sql->nextRec());
            }

            $this->set('is_writeform_show', $is_writeform_show);
            $this->set('is_writer_show', $is_writer_show);
            $this->set('is_guest_form_show', $is_guest_form_show);
            $this->set('print_arr', $print_arr);
            $this->set('board_id', $board_id);
            $this->set('read', $req['read']);
            $this->set('thisuri', $req['thisuri']);
            $this->set('total_cnt', $total_cnt);
        }
    }

    public function form()
    {
        $form = new \Controller\Make_View_Form();
        $form->set('id','commentForm');
        $form->set('type','html');
        $form->set('action',MOD_BOARD_DIR.'/controller/comment/comment-submit');
        $form->run();
    }

}

/***
Submit for Comment
***/
class Comment_submit {

    public function init()
    {
		global $MB, $req, $view, $boardconf, $board_id;

        $sql = new Pdosql();

        $boardlib = new Board_Library();

        Method::security('referer');
        Method::security('request_post');
        $req = Method::request('post', 'mode, board_id, read, thisuri, cidx, writer, comment, re_writer, re_comment, cmt_1, cmt_2, cmt_3, cmt_4, cmt_5, cmt_6, cmt_7, cmt_8, cmt_9, cmt_10');

        $board_id = $req['board_id'];

        //load config
        $boardconf = $boardlib->load_conf($board_id);

        //원본 글 정보 불러옴
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_data_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['read']
            )
        );
        $view = $sql->fetchs();

        //chkeck
        if ($boardconf['use_comment'] == 'N') {
            Valid::error('', '댓글 기능이 비활성화 중입니다.');
        }

        if ($MB['level'] > $boardconf['comment_level']) {
            Valid::error('', '댓글 사용 권한이 없습니다.');
        }

        if ($view['dregdate']) {
            Valid::error('', '삭제된 게시물의 댓글은 변경할 수 없습니다.');
        }

		switch ($req['mode']) {

			case 'write' :
    			$this->get_write();
    			break;

			case 'reply' :
    			$this->get_reply();
    			break;

			case 'modify' :
    			$this->get_modify();
    			break;

			case 'delete' :
    			$this->get_delete();
    			break;
		}
	}

	///
	// 새로운 댓글 작성
	///
    private function get_write()
    {
        global $MB, $view, $req, $boardconf, $board_id;

        $sql = new Pdosql();
        $Alarm_Library = new Alarm_Library();

        //check
        if (IS_MEMBER) {
            $mb_idx = $MB['idx'];
            $writer = $MB['name'];

        } else {
            $mb_idx = '';
            Valid::get(
                array(
                    'input' => 'writer',
                    'value' => $req['writer'],
                    'check' => array(
                        'defined' => 'nickname'
                    )
                )
            );
            $writer = $req['writer'];

        }
        Valid::get(
            array(
                'input' => 'comment',
                'value' => $req['comment'],
                'msg' => '댓글은 5자 이상 입력해야 합니다.',
                'check' => array(
                    'minlen' => 5
                )
            )
        );

        //ln값 처리
        $sql->query(
            "
            SELECT MAX(ln)+1000 AS ln_max
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE bo_idx=:col1
            ",
            array(
                $req['read']
            )
        );
        $ln_arr['ln_max'] = $sql->fetch('ln_max');
        if (!$ln_arr['ln_max']) {
            $ln_arr['ln_max'] = 1000;
        }
        $ln_arr['ln_max'] = (int)floor($ln_arr['ln_max'] / 1000) * 1000;

        //insert
        $sql->query(
            "
            INSERT INTO {$sql->table("mod:board_cmt_".$board_id)}
            (ln,rn,bo_idx,mb_idx,writer,comment,ip,regdate,cmt_1,cmt_2,cmt_3,cmt_4,cmt_5,cmt_6,cmt_7,cmt_8,cmt_9,cmt_10)
            VALUES
            (:col1,:col2,:col3,:col4,:col5,:col6,'{$_SERVER['REMOTE_ADDR']}',now(),:col7,:col8,:col9,:col10,:col11,:col12,:col13,:col14,:col15,:col16)
            ",
            array(
                $ln_arr['ln_max'], 0, $req['read'], $mb_idx, $writer, $req['comment'], $req['cmt_1'], $req['cmt_2'], $req['cmt_3'], $req['cmt_4'], $req['cmt_5'], $req['cmt_6'], $req['cmt_7'], $req['cmt_8'], $req['cmt_9'], $req['cmt_10']
            )
        );

        //게시글 작성자에게 알림 발송
        if ($view['mb_idx'] > 0 && $view['mb_idx'] != $MB['idx']) {
            $Alarm_Library->get_add_alarm(
                array(
                    'msg_from' => '게시판 ('.$boardconf['title'].')',
                    'from_mb_idx' => $MB['idx'],
                    'to_mb_idx' => $view['mb_idx'],
                    'memo' => '<strong>'.$writer.'</strong>님이 회원님의 게시글에 댓글을 남겼습니다.',
                    'link' => $req['thisuri'].'?mode=view&read='.$req['read']
                )
            );
        }

        //return
        Valid::set(
            array(
                'return' => 'callback',
                'function' => 'view_cmt_load()'
            )
        );
        Valid::turn();
    }

	///
	// 답변 댓글 작성
	///
    private function get_reply()
    {
        global $MB, $req, $boardconf, $board_id;

        $sql = new Pdosql();
        $Alarm_Library = new Alarm_Library();

        //check
        if (IS_MEMBER) {
            $mb_idx = $MB['idx'];
            $writer = $MB['name'];

        } else {
            $mb_idx = '';
            Valid::get(
                array(
                    'input' => 're_writer',
                    'value' => $req['re_writer']
                )
            );
            $writer = $req['re_writer'];
        }
        Valid::get(
            array(
                'input' => 're_comment',
                'value' => $req['re_comment'],
                'msg' => '댓글은 5자 이상 입력해야 합니다.',
                'check' => array(
                    'minlen' => 5
                )
            )
        );

        //원본 코멘트 정보
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['cidx']
            )
        );
        $comm_arr = $sql->fetchs();
        $bo_idx = (int)$sql->fetch('bo_idx');

        //rn 값 처리
        $rn = (int)$sql->fetch('rn');
        $rn_next = (int)$sql->fetch('rn') + 1;

        //ln값 처리
        $ln = (int)$sql->fetch('ln');
        $ln_min = (int)(floor($ln / 1000) * 1000);
        $ln_next = (int)$ln_min + 1000;

        //같은 레벨중 바로 아래 답글의 ln 값을 불러옴
        $sql->query(
            "
            SELECT
            IF( MIN(ln)>:col1,MIN(ln),:col2 ) ln
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE ln<:col2 AND ln>:col1 AND rn=:col3 AND bo_idx=:col4
            ",
            array(
                $ln, $ln_next, $rn, $bo_idx
            )
        );
        $ln_tar = $sql->fetch('ln');

        //댓글의 ln값 부여, 다른 댓글의 ln 정렬
        $sql->query(
            "
            SELECT IF( MAX(ln)>:col1,MAX(ln),:col1 ) ln
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE bo_idx=:col2 AND ln>:col1 AND ln<:col3 AND rn>:col4
            ",
            array(
                $ln, $bo_idx, $ln_tar, $rn
            )
        );

        $ln_isrt = $sql->fetch('ln') + 1;

        $sql->query(
            "
            UPDATE {$sql->table("mod:board_cmt_".$board_id)}
            SET ln=ln+1
            WHERE ln<:col1 AND ln>=:col2 AND rn>0
            ",
            array(
                $ln_next, $ln_isrt
            )
        );

        //insert
        $sql->query(
            "
            INSERT INTO {$sql->table("mod:board_cmt_".$board_id)}
            (ln,rn,bo_idx,mb_idx,writer,comment,ip,regdate,cmt_1,cmt_2,cmt_3,cmt_4,cmt_5,cmt_6,cmt_7,cmt_8,cmt_9,cmt_10)
            VALUES
            (:col1,:col2,:col3,:col4,:col5,:col6,'{$_SERVER['REMOTE_ADDR']}',now(),:col7,:col8,:col9,:col10,:col11,:col12,:col13,:col14,:col15,:col16)
            ",
            array(
                $ln_isrt, $rn_next, $req['read'], $mb_idx, $writer, $req['re_comment'], $req['cmt_1'], $req['cmt_2'], $req['cmt_3'], $req['cmt_4'], $req['cmt_5'], $req['cmt_6'], $req['cmt_7'], $req['cmt_8'], $req['cmt_9'], $req['cmt_10']
            )
        );

        //부모 댓글 작성자에게 알림 발송
        if ($comm_arr['mb_idx'] > 0 && $comm_arr['mb_idx'] != $MB['idx']) {
            $Alarm_Library->get_add_alarm(
                array(
                    'msg_from' => '게시판 ('.$boardconf['title'].')',
                    'from_mb_idx' => $MB['idx'],
                    'to_mb_idx' => $comm_arr['mb_idx'],
                    'memo' => '<strong>'.$writer.'</strong>님이 회원님의 댓글에 대댓글을 남겼습니다.',
                    'link' => $req['thisuri'].'?mode=view&read='.$req['read']
                )
            );
        }

        //return
        Valid::set(
            array(
                'return' => 'callback',
                'function' => 'view_cmt_load()'
            )
        );
        Valid::turn();
    }

	///
	// 댓글 수정
	///
    private function get_modify()
    {
        global $MB, $req, $boardconf, $board_id;

        $sql = new Pdosql();

        //원본 글 정보
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['cidx']
            )
        );
        $arr = $sql->fetchs();

        //check
        if ($sql->getcount() < 1 ){
            $this->error('', '존재하지 않는 댓글입니다.');
        }
        if ($arr['mb_idx'] != $MB['idx'] && $MB['level'] > $boardconf['ctr_level'] && $MB['adm'] != 'Y') {
            Valid::error('', '자신의 댓글만 수정 가능합니다.');
        }

        Valid::get(
            array(
                'input' => 're_comment',
                'value' => $req['re_comment'],
                'msg' => '댓글은 5자 이상 입력해야 합니다.',
                'check' => array(
                    'minlen' => 5
                )
            )
        );

        //update
        if (IS_MEMBER && $arr['mb_idx'] == MB_IDX) {
            $writer = $MB['name'];

        } else {
            $writer = $arr['writer'];
        }

        $sql->query(
            "
            UPDATE {$sql->table("mod:board_cmt_".$board_id)}
            SET writer=:col1,comment=:col2,ip='{$_SERVER['REMOTE_ADDR']}',cmt_1=:col3,cmt_2=:col4,cmt_3=:col5,cmt_4=:col6,cmt_5=:col7,cmt_6=:col8,cmt_7=:col9,cmt_8=:col10,cmt_9=:col11,cmt_10=:col12
            WHERE idx=:col13
            ",
            array(
                $writer, $req['re_comment'], $req['cmt_1'], $req['cmt_2'], $req['cmt_3'], $req['cmt_4'], $req['cmt_5'], $req['cmt_6'], $req['cmt_7'], $req['cmt_8'], $req['cmt_9'], $req['cmt_10'], $req['cidx']
            )
        );

        //return
        Valid::set(
            array(
                'return' => 'callback',
                'function' => 'view_cmt_load()'
            )
        );
        Valid::turn();
    }

	///
	// 댓글 삭제
	///
    private function get_delete()
    {
        global $MB, $req, $boardconf, $board_id;

        $sql = new Pdosql();

        //chkeck
        $sql->query(
            "
            SELECT *
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['cidx']
            )
        );
        $arr = $sql->fetchs();

        if ($sql->getcount() < 1) {
            Valid::error('', '존재하지 않는 댓글입니다.');
        }

        if ((!$arr['mb_idx'] || $arr['mb_idx'] != $MB['idx']) && $MB['level'] > $boardconf['ctr_level'] && $MB['adm'] != 'Y') {
            Valid::error('', '자신의 댓글이 아니거나, 삭제 권한이 없습니다.');
        }

        //하위 자식 댓글이 있는경우 삭제 금지
        $ln_min = (int)(ceil($arr['ln'] / 1000) * 1000);
        $ln_max = (int)(ceil($arr['ln'] / 1000) * 1000) + 1000;

        //부모글인 경우 색인 조건문 만듬
        if ($arr['rn'] == 0) {
            $sql->query(
                "
                SELECT *
                FROM {$sql->table("mod:board_cmt_".$board_id)}
                WHERE ln<:col1 AND ln>=:col2 AND rn>=:col3 AND bo_idx=:col4
                ",
                array(
                    $ln_max, $ln_min, 0, $req['read']
                )
            );

        //답글이 있는지 검사
        } else if ($arr['rn'] >= 1) {

            $sql->query(
                "
                SELECT *
                FROM {$sql->table("mod:board_cmt_".$board_id)}
                WHERE ln<:col1 AND ln>=:col2 AND rn>=:col3 AND bo_idx=:col4
                ",
                array(
                    $ln_max, $arr['ln'], $arr['rn'], $req['read']
                )
            );
            $ln_arr['ln'] = $sql->fetch('ln');

            if ($ln_arr['ln'] == '') {
                $wr_ln = $ln_max;

            } else {
                $wr_ln = $ln_arr['ln'];
            }

            $sql->query(
                "
                SELECT *
                FROM {$sql->table("mod:board_cmt_".$board_id)}
                WHERE ln<:col1 AND ln>=:col2 AND rn>=:col3 AND bo_idx=:col4
                ",
                array(
                    $wr_ln, $arr['ln'], $arr['rn'], $req['read']
                )
            );
        }

        if ($sql->getcount() > 1) {
            Valid::error('', '답글이 있는 경우 삭제가 불가능 합니다.');
        }

        //delete
        $sql->query(
            "
            DELETE
            FROM {$sql->table("mod:board_cmt_".$board_id)}
            WHERE idx=:col1
            ",
            array(
                $req['cidx']
            )
        );

        //return
        Valid::set(
            array(
                'return' => 'callback',
                'function' => 'view_cmt_load()',
            )
        );
        Valid::turn();
    }

}
