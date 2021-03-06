<?php

namespace Admin\Controller;
use User\Api\UserApi as UserApi;
use Think\Controller;

class MainController extends AdminController {

    public $a = '';

    /**提取起止日期**/
    private function _queryTime(){
        $start_time = strtotime(I('start_time'));
        $end_time   = strtotime(I('end_time'));
        if($start_time || $end_time){
            if($start_time >= $end_time){
                $this -> error('查询的开始日期大于结束日期，这让我很为难啊...');
            }else{
                $map['create_time'] = array(array('gt', $start_time), array('lt', $end_time));
            }
        }
        return $map;
    }


    public function index(){
        //Nothing need to do here.
    }


    /**推广专员信息管理及导出**/
    public function msgList($method = null){
        $dbStaff = D('Staff');
        $dbStaffInfo = D('StaffInfo');
        $map = $this -> _queryTime();
        $staff_name = I('staff_name');
        $map['status'] = array('egt',0);
        if($staff_name) {
            if (is_numeric($staff_name)) {
                $map["id|staff_name"] = array(intval($staff_name), array('like', '%' . $staff_name . '%'), '_multi' => true);
            } else {
                $map['staff_name'] = array('like', '%' . (string)$staff_name . '%');
            }
        }
        $resStaff = $this -> lists($dbStaff);
        foreach($resStaff as $k => $v){
            $id = $resStaff[$k]['id'];
            $info = $dbStaffInfo -> where("uid = $id") -> find();
            $resStaff[$k]['class']          = $info['class'];
            $resStaff[$k]['credit_value']   = $info['credit_value'];
            $resStaff[$k]['credit_num']     = $info['credit_num'];
            $resStaff[$k]['fix_bonus']      = $info['fix_bonus'];
            $resStaff[$k]['extra_bonus']    = $info['extra_bonus'];
        }
        //导出查询到的数据（不含分页）
        $excel = A('Excel');
        $xlsCell = array(
            array('id', 'ID'),
            array('staff_name', '昵称'),
            array('staff_real', '真实姓名'),
            array('mobile', '手机号'),
            array('card_id', '身份证号'),
            array('referee', '推荐人'),
            array('game_id', '游戏ID'),
            array('money', '余额'),
            array('consume_coin', '消费币'),
            array('create_time', '注册时间'),
            array('status', '状态'),
        );
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $end_time = $map['create_time'];
        if(empty($end_time) && empty($staff_name)){
            $xlsName = 'Staff全表导出';
        }elseif(empty($end_time) && $staff_name){
            $xlsName = 'Staff表专员搜索结果导出';
        }else {
            $xlsName = 'Staff表搜索结果导出';
        }
        $xlsData = $dbStaff -> get_all_msg($field,$map);
        foreach ($xlsData as $k => $v) {
            $xlsData[$k]['create_time'] = $v['create_time'] == 0 ? '-' : $v['create_time'];
            $xlsData[$k]['status']      = $v['status']      == 1 ? '正常' : '禁用';
        }
        switch (strtolower($method)){
            case 'list':
                $resStaff = $this -> lists($dbStaff,$map);
                break;
            case 'out':
                $excel->exportExcel($xlsName,$xlsCell,$xlsData);
                break;
        }
        int_to_string($resStaff);
        $this -> assign('resStaff',$resStaff);
        $this -> meta_title = '推广专员信息管理';
        $this -> display('Main/Msg/msgList');
    }


    /**修改推广专员信息**/
    public function msgEdit(){
        $dbStaff = D('staff');
        $where = I('id');
        if(IS_POST){
            $id = $_POST['id'];
            $data = array(
                'staff_name' => I('name'),
                'referee'    => I('referee'),
                'mobile'     => I('mobile'),
                'staff_real' => I('real'),
                'card_id'    => I('card'),
                'game_id'    => I('game_id'),
            );
            $resStaff = $dbStaff -> msg_save($id,$data);
            if($resStaff == 0){
                $this -> success('什么都没修改，正跳转至列表页...',U('Main/msgList'));
            }elseif($resStaff == 1){
                $this -> success('修改成功',U('Main/msgList'));
            }else{
                $this -> error('修改失败');
            }
        }else{
            $resStaffEdit = $dbStaff -> msg_find($where);
            $this->assign('resStaffEdit', $resStaffEdit);
            $this->meta_title = '修改推广专员信息';
            $this->display('Main/Msg/msgEdit');
        }
    }


    /**添加推广专员**/
    public function msgAdd(){
        if(IS_POST) {
            $dbStaff    = D('Staff');
            $ref        = $dbStaff -> get_staff('mobile',I('referee_mobile'));
            if(I('game_id')){
                $gameID = $dbStaff -> get_staff('game_id',I('game_id'));
                if($gameID){
                    echo "<script>alert('请注意，此游戏ID已被绑定过，若要修改，请在添加完成后“编辑”此推广专员中修改');</script>";
                }
            }
            if($ref){
                $data = array(
                    'mobile'        => I('mobile'),
                    'game_id'       => I('game_id'),
                    'card_id'       => I('card_id',''),
                    'address'       => I('addr'),
                    'referee'       => $ref['id'],
                    'staff_real'    => I('ture_name'),
                    'staff_pwd'     => md5('teb123456'),
                    'service_number'=> $ref['service_number'],
                );
                $insert             = $dbStaff -> msg_insert($data);
                if($insert == 1){
                    //分享人recommend_num+1
                    $recommedAdd    = $dbStaff -> where(array('id' => $ref['id'])) -> setInc('recommend_num');
                    //分享人income补差价
                    $recomNum       = $dbStaff -> get_staff_by_referee($ref['id'], 'count');
                    $moneyGive      = $dbStaff -> referee_given($recomNum);
                    $incomeGiven    = $dbStaff -> where(array('id' => $ref['id'])) -> setInc('income', $moneyGive);
                    //中心金额数据
                    $sericeAward    = service_awards('register', $ref['service_number'], '1000');
                    foreach($sericeAward as $k => $v){
                        //发放奖励
                        $staffData  = array('money' => $v['fact_money'], 'income' => $v['money'], 'consume_coin' => $v['coin'],);
                        $staffArr[] = array('id' => $v['id'], 'data' => $staffData,);
                        //流水表 flow
                        $dataFlow   = array('uid' => $v['id'], 'type' => 6, 'money' => $v['money'], 'order_id' => $v['order_id'], 'create_time' => date('Y-m-d H:i:s'),);
                        $flowArr[]  = array('id' => $v['id'], 'data' => $dataFlow,);
                        //奖励表 reward
                        $dataReward = array('uid' => $v['id'], 'type' => 5, 'money' => $v['fact_money'], 'game_coin' => $v['coin'], 'order_id' => $v['order_id'], 'create_time' => date('Y-m-d H:i:s'), 'remarks' => "恭喜您的团队推荐成功注册一名推广专员，奖励￥".$v['money'],);
                        $rewardArr[]= array('id' => $v['id'], 'data' => $dataReward,);
                        //通知表 notice
                        $dataNotices= array('uid' => $v['id'], 'kind' => '2', 'poster' => 'system', 'notice_type_id' => 3, 'notice_title' => '兑换中心分享奖励', 'create_time' => date('Y-m-d H:i:s'), 'notice_content' => "恭喜您的团队推荐成功注册一名推广专员，奖励￥".$v['money'],);
                        $noticeArr[]= array('id' => $v['id'], 'data' => $dataNotices,);
                    }
                    $service        = pay_reward($staffArr, $rewardArr, $flowArr, $noticeArr);
                    if($recommedAdd == 1 && $incomeGiven == 1 && $service == 1){
                        $this->success('推广专员增加且各项奖励发放成功', U('Main/msgList'));
                    }elseif($recommedAdd == 0){
                        $this -> error('推荐人分享数增加失败');
                    }elseif($incomeGiven == 0){
                        $this -> error('推荐人income补差价增加失败');
                    }elseif($service == 2){
                        $this -> error('中心奖励发放失败');
                    }
                }else{
                    $this->error($dbStaff->getError());
                }
            }else{
                $this -> error('信息填写不完整或推荐人手机号不存在，请重新填写！');
            }
        }else {
            $this->meta_title = '添加推广专员';
            $this->display('Main/Msg/msgAdd');
        }
    }


    /**一键发放推荐奖励**/
    public function givenReward(){
        if(handle_settle() == 1){
            $this -> success('奖励发放成功', U('Main/msgList'));
        }else{
            $this -> error('奖励发放失败');
        }
    }


    /**状态修改**/
    public function changeStatus($method=null,$dbname=null){
        $id = array_unique((array)I('id',0));
        $id = in_array($id) ? implode(',',$id) : $id;
        if ( empty($id) || $id[0] == 0) {
            $this->error('请选择要操作的数据!');
        }
        $map['uid'] =   array('in',$id);
        switch ( strtolower($method) ){
            case 'forbiduser':
                $this->forbid($dbname, $map );
                break;
            case 'resumeuser':
                $this->resume($dbname, $map );
                break;
            case 'deleteuser':
                $this->delete($dbname, $map );
                break;
            case 'ipost':
                $abc = $this -> a = $id;
                $bc['id'] = array('in',$abc);
                return $bc;
                break;
            default:
                $this->error('参数非法');
        }
    }


    /**任务列表及导出**/
    public function taskList($method=null){
        $dbTask = D('Task');
        $timeNew = A('Cash');
        $map = $timeNew -> _queryCreateTime();
        $task_name = I('task_name');
        $type = I('type');
        if($type) {
            $map['type'] = $type;
        }
        $this -> assign('type',$type);
        if($task_name) {
            if (is_numeric($task_name)) {
                $map['id|name'] = array(intval($task_name), array('like', '%' . $task_name . '%'), '_multi' => true);
            } else {
                $map['name'] = array('like', '%' . (string)$task_name . '%');
            }
        }
        $resTask = $this -> lists($dbTask);
        //导出查询到的数据（不含分页）
        $excel = A('Excel');
        $xlsCell = array(
            array('id', 'ID'),
            array('name', '任务名称'),
            array('detail', '任务描述'),
            array('class', '任务等级'),
            array('isgame', '是否游戏任务'),
            array('type', '任务类型'),
            array('inneed', '任务指标'),
            array('create_time', '创建时间'),
            array('money', '任务金额'),
            array('tasker', '发布者'),
            array('status', '状态'),
        );
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $end_time = $map['create_time'];
        if(empty($end_time) && empty($task_name) && empty($type) && empty($status)){
            $xlsName = 'Task全表导出';
        }elseif($task_name){
            $xlsName = 'Task表专员搜索结果导出';
        }else {
            $xlsName = 'Task表搜索结果导出';
        }
        $type = array('-','基本任务','额外任务');
        $xlsData = $dbTask -> get_all_task($field,$map);
        foreach ($xlsData as $k => $v) {
            $xlsData[$k]['create_time'] = $v['create_time'] == 0 ? '-' : $v['create_time'];
            $xlsData[$k]['isgame']      = $v['isgame']      == 0 ? '是': '否';
            $xlsData[$k]['tasker']      = $v['tasker']      =='' ? '-' : $v['tasker'];
            $xlsData[$k]['type']        = $type[$v['type']];
            $xlsData[$k]['status']      = $v['status']      == 0 ? '无效' : '有效';
        }
        switch (strtolower($method)){
            case 'list':
                $resTask = $this -> lists($dbTask,$map);
                break;
            case 'out':
                $excel->exportExcel($xlsName,$xlsCell,$xlsData);
                break;
        }
        int_to_string($resTask);
        $this -> assign('resTask',$resTask);
        $this -> meta_title = '任务列表';
        $this -> display('Main/Task/taskList');
    }


    /**添加日常任务**/
    public function daliyTaskAdd(){
        $dbTask = D('Task');
        if(IS_POST) {
            $data = array(
                'name'      => I('name', '', 'htmlspecialchars'),
                'type'      => 1,
                'class'     => I('post.class'),
                'inneed'    => I('post.inneed'),
                'detail'    => I('post.detail'),
                'money'     => I('post.money'),
                'tasker'    => session('user_auth')['username'],
            );
            if($dbTask -> task_insert($data)){
                $this -> success('增加成功', U('Main/taskList'));
            }else{
                $this -> error($dbTask -> getError());
            }
        }else {
            $this -> meta_title = '添加日常任务';
            $this -> display('Main/Task/daliyTaskAdd');
        }
    }


    /**添加额外任务**/
    public function extraTaskAdd(){
        $dbTask = D('Task');
        if(IS_POST) {
            $data = array(
                'name'      => I('name', '', 'htmlspecialchars'),
                'type'      => 2,
                'detail'    => I('post.detail'),
                'money'     => I('post.money'),
                'tasker'    => session('user_auth')['username'],
            );
            if($dbTask -> add($data)){
                $this -> success('增加成功', U('Main/taskList'));
            }else{
                $this -> error($dbTask -> getError());
            }
        }else {
            $this -> meta_title = '添加额外任务';
            $this -> display('Main/Task/extraTaskAdd');
        }
    }

    /**发布周任务**/
    public function taskPost($abc = null){
        $dbTask = D('Task');
        $dbTaskWeekly = M('TaskWeekly');
        date_default_timezone_set('PRC');
        // Begin: the 3 line codes below are for getting next Monday 2:00 am and next Sunday 23:59:59
        $start_time = date('Y-m-d 02:00:00',strtotime('Monday'));  //TODO 待定
        $ss = strtotime($start_time);
        $end_time = date('Y-m-d 23:59:59',strtotime('Sunday',$ss));
        $data = array(
            'start_time'=> $start_time,
            'end_time'  => $end_time,
            'post_time' => date('Y-m-d H:i:s'),
            'tasker'    => session('user_auth')['username'],
            'status'    => '3',
        );
        $date['status'] = 1;
        if($abc == 2) {
            $task_id = $this -> changeStatus('ipost', null);
            $resTask = $dbTask -> where($task_id) -> select(); //数组查询结果
            foreach($resTask as $k => $v){
                $new = $resTask[$k]['id'];
                $data['task_id']= $resTask[$k]['id'];
                $data['name']   = $resTask[$k]['name'];
                $data['type']   = $resTask[$k]['type'];
                if($resTask[$k]['status'] == 0){    //避免重复发布
                    $resTaskWeekly  = $dbTaskWeekly -> add($data);
                    $result = $dbTask -> save_task_by_id($new, $date);
                }
            }
        }else {
            $task_id = I('id');
            $resTask = $dbTask -> get_task_by_id($task_id);
            $data['task_id']    = $task_id;
            $data['name']       = $resTask['name'];
            if($resTask['status'] == 0) {       //避免重复发布
                $resTaskWeekly = $dbTaskWeekly -> add($data);
                $result = $dbTask -> save_task_by_id($task_id, $date);
            }
        }
        if($resTaskWeekly && $result){
            $this -> success('发布成功',U('Main/taskList'));
        }else{
            $this -> error('发布失败,发布时请勿选择已发布的任务！');
        }
    }


    /**周计划任务**/
    public function taskWeekly(){
        $dbTaskWeekly = D('TaskWeekly');
        $dbTask = D('Task');
        $task_name = I('task_name');
        $timeNew = A('Cash');
        $map = $timeNew -> _queryPostTime();
        if($task_name) {
            if (is_numeric($task_name)) {
                $map['id|name'] = array(intval($task_name), array('like', '%' . $task_name . '%'), '_multi' => true);
            } else {
                $map['name'] = array('like', '%' . (string)$task_name . '%');
            }
        }
        $resWeekly = $dbTaskWeekly -> get_all_weekly('',$map);
        $time = date('Y-m-d H:i:s');
        foreach($resWeekly as $k => $v){
            $where = $resWeekly[$k]['task_id'];
            switch($time) {
                case $time < $resWeekly[$k]['start_time'] :
                    $res['status'] = '3';
                    break;
                case ($resWeekly[$k]['start_time']) < $time && ($resWeekly[$k]['end_time']) > $time:
                    $res['status'] = '1';
                    break;
                case $resWeekly[$k]['end_time'] < $time:
                    $res['status'] = '2';
                    break;
            }
            $dbTaskWeekly->save_weekly_by_id($where, $res);
        }
        $resTaskWeekly = $this -> lists($dbTaskWeekly,$map);
        foreach($resTaskWeekly as $k => $v){
            $where = $resWeekly[$k]['task_id'];
            if(!empty($where)) {
                $resTask = $dbTask->get_task_by_id($where);
                $resTaskWeekly[$k]['type']      = $resTask['type'];
                $resTaskWeekly[$k]['inneed']    = $resTask['inneed'];
                $resTaskWeekly[$k]['money']     = $resTask['money'];
                $resTaskWeekly[$k]['class']     = $resTask['class'];
                $resTaskWeekly[$k]['detail']    = $resTask['detail'];
            }
        }
        int_to_string($resTaskWeekly);
        $this -> assign('resTaskWeekly',$resTaskWeekly);
        $this -> meta_title = '周计划任务';
        $this -> display('Main/Task/taskWeekly');
    }


    /**编辑日常任务**/
    public function dailyTaskEdit(){
        $dbTask = D('Task');
        $where = I('id');
        if(IS_POST){
            $id = $_POST['id'];
            $data = array(
                'name'      => I('name', '', 'htmlspecialchars'),
                'type'      => 1,
                'class'     => I('post.class'),
                'inneed'    => I('post.inneed'),
                'detail'    => I('post.detail'),
                'money'     => I('post.money'),
                'tasker'    => session('user_auth')['username'],
            );
            $resStaff = $dbTask -> save_task_by_id($id,$data);
            if($resStaff == 0){
                $this -> success('什么都没更改，正跳转至列表页...',U('Main/taskList'));
            }elseif($resStaff == 1){
                $this -> success('修改成功',U('Main/taskList'));
            }else{
                $this -> error('修改失败');
            }
        }else {
            $resTask = $dbTask -> get_task_by_id($where);
            $this->assign('resTask', $resTask);
            $this->meta_title = '编辑日常任务';
            $this->display('Main/Task/dailyTaskEdit');
        }
    }


    /**编辑日常任务**/
    public function extraTaskEdit(){
        $dbTask = D('Task');
        $where = I('id');
        if(IS_POST){
            $id = $_POST['id'];
            $data = array(
                'name'      => I('name', '', 'htmlspecialchars'),
                'type'      => 2,
                'detail'    => I('post.detail'),
                'tasker'    => session('user_auth')['username'],
            );
            $resStaff = $dbTask -> save_task_by_id($id,$data);
            if($resStaff == 0){
                $this -> success('什么都没更改，正跳转至列表页...',U('Main/taskList'));
            }elseif($resStaff == 1){
                $this -> success('修改成功',U('Main/taskList'));
            }else{
                $this -> error('修改失败');
            }
        }else {
            $resTask = $dbTask -> get_task_by_id($where);
            $this->assign('resTask', $resTask);
            $this->meta_title = '编辑额外任务';
            $this->display('Main/Task/extraTaskEdit');
        }
    }


    /**取消发布周任务**/
    public function taskWeeklyCancle($abc = null){
        $dbTask = D('Task');
        $dbTaskWeekly = D('TaskWeekly');
        $data['status'] = 0;
        if($abc == 2) {
            $id = $this -> changeStatus('ipost', null);
            $resWeekly = $dbTaskWeekly -> where($id) -> select(); //数组查询结果
            foreach($resWeekly as $k => $v){
                $task_id = $resWeekly[$k]['task_id'];
                $resTask = $dbTask -> save_task_by_id($task_id, $data);
                $nid = $dbTaskWeekly -> where("task_id = $task_id") -> select();
                foreach($nid as $kk => $vv){
                    $nids  = $nid[$kk]['id'];
                    $resTaskWeekly = $dbTaskWeekly -> delete_weekly_by_id($nids);
                }
            }
        }else{
            $id = I('id');
            $resWeekly = $dbTaskWeekly-> get_weekly_by_id($id);
            $task_id = $resWeekly['task_id'];
            $resTask = $dbTask -> save_task_by_id($task_id, $data);
            $resTaskWeekly = $dbTaskWeekly -> delete_weekly_by_id($id);
        }
        if($resTask && $resTaskWeekly){
            $this -> success('取消发布成功',U('Main/taskWeekly'));
        }else{
            $this -> error('取消发布失败');
        }
    }


    /**已完成任务**/
    public function taskDone(){
        $dbTask     = D('Task');
        $dbTaskDone = D("TaskDone");
        $map['task_id'] = array('gt',0);
        $uid = I('uid');
        if($uid) {
            $map['id|uid|task_id'] = $uid;
        }
        $resTaskDone = $this -> lists($dbTaskDone,$map);
        foreach($resTaskDone as $k => $v){
            $task_id = $resTaskDone[$k]['task_id'];
            $resTask = $dbTask -> get_task_by_id($task_id);
            $resTaskDone[$k]['name']    = $resTask['name'];
            $resTaskDone[$k]['money']   = $resTask['money'];
            $resTaskDone[$k]['type']    = $resTask['type'];
        }
        $this->assign('resTaskDone',$resTaskDone);
        $this->meta_title = '已完成任务';
        $this->display('Main/Task/taskDone');
    }


    /**查看已完成任务**/
    public function taskDoneView(){
        $dbTask     = D('Task');
        $dbStaff    = D('Staff');
        $dbTaskDone = D("TaskDone");
        $id = I('id');
        $resTaskDone = $dbTaskDone -> get_done_by_id($id);
        $resStaff = $dbStaff -> msg_find($resTaskDone['uid']);
        $staff = array(
            'staff_name'    => $resStaff['staff_name'],
            'staff_real'    => $resStaff['staff_real'],
            'mobile'        => $resStaff['mobile'],
            'game_id'       => $resStaff['game_id'],
        );
        $resTask = $dbTask -> get_task_by_id($resTaskDone['task_id']);
        $this->assign('resTaskDone',$resTaskDone);
        $this->assign('staff',$staff);
        $this->assign('resTask',$resTask);
        $this->meta_title = '查看已完成任务';
        $this->display('Main/Task/taskDoneView');

    }


    /**消息管理**/
    public function notice($method = null){
        $dbNotice = D('notice');
        $noticeType = M('notice_type');
        $cash = A('Cash');
        $map = $cash -> _queryCreateTime();
        $notice_name = I('notice_name');
        $notice_type = I('notice_type');
        $this -> assign('notice_type',$notice_type);
        if($notice_name) {
            if (is_numeric($notice_name)) {
                $map['id|notice_title'] = array(intval($notice_name), array('EQ', $notice_name));
            } else {
                $map['notice_title'] = array('like', '%' . $notice_name . '%');
            }
        }
        if($notice_type){
            $map['notice_type_id'] = $notice_type;
        }
        $map['kind'] = 1;
        $resNotice = $this -> lists($dbNotice,$map);
        foreach ($resNotice as $k => $v) {
            if(strlen($resNotice[$k]['notice_content']) < 40){
                $resNotice[$k]['n_content'] = $resNotice[$k]['notice_content'];
            }else{
                $resNotice[$k]['n_content'] = msubstr($resNotice[$k]['notice_content'],0,40);
            }
            $resNotice[$k]['s_content']  = str_replace('<br/>','',$resNotice[$k]['n_content']);//屏蔽<br/>
            $id['id'] = $resNotice[$k]['notice_type_id'];
            $type = $noticeType -> where($id) -> find();
            $resNotice[$k]['notice_type_name'] = $type['notice_type_name'];
        }
        $where['id'] = array('in','1,2,4');
        $type = $noticeType -> where($where)-> select();
        $this -> assign('type',$type);
        $this -> assign('resNotice',$resNotice);
        //导出查询到的数据（不含分页）
        $excel = A('Excel');
        $xlsCell = array(
            array('id', 'ID'),
            array('notice_title', '公告主题'),
            array('notice_content', '功能内容'),
            array('notice_type_id', '公告类型'),
            array('create_ip', '发布人IP'),
            array('poster', '发布人'),
            array('create_time', '创建时间'),
        );
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $end_time = $map['create_time'];
        if(empty($end_time) && empty($notice_name) && empty($notice_type)){
            $xlsName = 'Notice全表导出';
        }elseif($notice_name){
            $xlsName = 'Notice表通知主题搜索结果导出';
        }else {
            $xlsName = 'Notice表搜索结果导出';
        }
        $xlsData = $dbNotice -> get_all_notice($field,$map);
        foreach ($xlsData as $k => $v) {
            $xlsData[$k]['create_time'] = $v['create_time'] == 0 ? '-' : $v['create_time'];
            $xlsData[$k]['status']      = $v['status']      == 1 ? '正常' : '禁用';
        }
        switch (strtolower($method)){
            case 'list':
                //nothing need to do here!
                break;
            case 'out':
                $excel->exportExcel($xlsName,$xlsCell,$xlsData);
                break;
        }
        $this -> meta_title = '消息管理';
        $this -> display('Main/Notice/notice');
    }


    /**发布消息**/
    public function noticePost(){
        if($_POST)
        {
            //提交公告内容信息
            if($_POST['notice_type']>0&&$_POST['notice_title']&&$_POST['content']){
                $notice = D('notice');
                $data['create_ip'] = $_SERVER['REMOTE_ADDR'];
                $data['poster'] = session('user_auth')['username'];
                $data['notice_content'] = $_POST['content'];
                $data['notice_type_id'] = $_POST['notice_type'];
                $data['notice_title'] = $_POST['notice_title'];
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['uid'] = 0;
                $data['kind'] = 1;
                /*
                //上传图片信息
                $upload = new \Think\Upload();
                $upload->maxSize   =     2*1024*1024 ;// 设置附件上传大小
                $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                $upload->rootPath  =     './Uploads/Picture/'; // 设置附件上传根目录
                $info   =   $upload->uploadOne($_FILES['photo1']);//上传文件信息
                $savepath = $info['savepath'];//存放图片的文件夹名
                $savename = $info['savename'];//图片名
                $img_url = 'http://'.$_SERVER['HTTP_HOST'].'/Uploads/Picture/'.$savepath.$savename;//拼接图片地址
                $data['img_url'] = $img_url;//存放图片路径
                */
                if($notice->add($data)){
                    $this -> success('提交成功',U('Main/notice'));
                }else{
                    $this -> error('提交失败');
                }
            }
            else{
                echo "<script>alert('请正确输入信息');history.go(-1); </script>";
            }
        }else {
            $where['id'] = array('in','1,2,4');
            $result = M('notice_type')->where($where)->select();
            $this->assign('type', $result);
            $this->meta_title = '消息管理';
            $this->display('Main/Notice/noticePost');
        }
    }


    /**修改消息**/
    public function noticeEdit()
    {
        $dbNotice = M('notice');
        $dbNoticeType = M('notice_type');
        $noticeID = I('id');
        $resNotice = $dbNotice -> where(array('id='.$noticeID))->find();
        $resNoticeType = $dbNoticeType -> where(array('id='. $resNotice['notice_type_id']))->find();//$resNoticeType['notice_type_name']消息类型
        $resNoticeAllType = $dbNoticeType -> select();
        if(IS_POST)
        {
            header('Content-Type:text/html;charset=utf-8');
            $noticeTypeName = $_POST['noticeType'];
            $resNoticeTypeData = $dbNoticeType -> where(array('notice_type_name' => $noticeTypeName))->find();
            $resNoticeTypeID = $resNoticeTypeData['id'];
            $data = array(
                'id'            => I('post.noticeID'), //消息id
                'notice_title'  => I('post.noticeTitle'),//消息标题
                'notice_content'=> I('noticeContent'),//消息内容
                'create_time'   => date('Y-m-d H:i:s'),
                'create_ip'     => $_SERVER['REMOTE_ADDR'],
                'notice_type_id'=> $resNoticeTypeID,
            );
            /*
            //图片信息
            $upload = new \Think\Upload();
            $upload->maxSize   =     2*1024*1024 ;// 设置附件上传大小
            $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
            $upload->rootPath  =     './Uploads/Picture/'; // 设置附件上传根目录
            $info   =   $upload->uploadOne($_FILES['photo']);//上传文件信息
            //$info   =   $upload->uploadOne($_FILES['photo']);//上传文件信息
            $savepath = $info['savepath'];//存放图片的文件夹名
            $savename = $info['savename'];//图片名
            $img_url = 'http://'.$_SERVER['HTTP_HOST'].'/Uploads/Picture/'.$savepath.$savename;//拼接图片地址
            $data['img_url'] = $img_url;//存放图片路径
            */
            $res = $dbNotice->where(array('id='.$data['id']))->save($data);
            if($res){
                $this -> success('修改成功',U('Main/notice'));
            }else{
                $this -> error('修改失败');
            }
        }else {
            $this->assign('noticeID', $resNotice);
            $this->assign('noticeTitle', $resNotice);
            $this->assign('noticeContent', $resNotice);
            $this->assign('noticeType', $resNoticeType);
            $this->assign('noticeAllType', $resNoticeAllType);
            $this->meta_title = '修改消息信息';
            $this->display('Main/Notice/noticeEdit');
        }
    }


    /**删除消息**/
    public function noticeDelete()
    {
        $notice = D('notice');
        $noticeDeleteId = I('id');//获取删除的ID
        $notice->where('id='.$noticeDeleteId)->delete(); // 删除id为5的用户数据
        $this -> success('删除成功',U('Main/notice'));
    }


    /**金额变动消息**/
    public function moneyNotice(){
        $dbNotice = D('notice');
        $cash = A('Cash');
        $map = $cash -> _queryCreateTime();
        $notice_name = I('notice_name');
        if($notice_name) {
            if (is_numeric($notice_name)) {
                $map['uid|notice_title'] = array(intval($notice_name), array('EQ', $notice_name));
            } else {
                $map['notice_title'] = array('like', '%' . $notice_name . '%');
            }
        }
        $map['kind'] = 2;
        $resNotice = $this -> lists($dbNotice,$map);
        $this -> assign('resNotice',$resNotice);
        $this -> meta_title = '金额变动消息';
        $this -> display('Main/Notice/moneyNotice');
    }


    /**关系管理详情**/
    public function relationDetail(){
        $dbStaff        = D('Staff');
        $dbUserShip     = D('UserShip');
        $dbUserCharge   = D('UserCharge');
        $staffId        = I('id');
        $staff = $dbStaff -> msg_find($staffId);
        //获取伞下推广专员
        $staffReferee = $dbStaff -> get_staff_by_referee($staffId,'select');
        foreach($staffReferee as $k => $v){
            $refereeId[] = $staffReferee[$k]['id'];
        }
        $refereeIds = implode(',',$refereeId);
        //获取个人充值总金额
        $gameId     = $staff['game_id'];
        $chargeSelf = $dbUserCharge -> get_user_charge($gameId,'2','money');
        //获取伞下玩家信息
        $superior = $dbUserShip -> get_user_by_superior($staffId,'select');
        foreach($superior as $k => $v){
            $superiorGameId[] = $superior[$k]['game_id'];
        }
        //伞下玩家
        $superiorGameIds = implode(',',$superiorGameId);
        //获取伞下玩家充值总金额
        $chargeBlow = $dbUserCharge -> get_user_charge($superiorGameId,'2','money');
        $this -> assign('staff',$staff);
        $this -> assign('chargeBlow',$chargeBlow);
        $this -> assign('chargeSelf',$chargeSelf);
        $this -> assign('refereeIds',$refereeIds);
        $this -> assign('superiorGameIds',$superiorGameIds);
        $this -> meta_title = '查看关系';
        $this -> display('Main/Msg/relationDetail');
    }


    /**接口基本设置**/
    public function apiSet(){

        $this -> meta_title = '接口基本设置';
        $this -> display('Main/apiSet/apiSet');
    }


    /**添加接口**/
    public function apiAdd(){

        $this -> meta_title = '添加接口';
        $this -> display('Main/apiSet/apiAdd');
    }

}
