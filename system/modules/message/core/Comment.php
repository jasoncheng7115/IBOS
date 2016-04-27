<?php

namespace application\modules\message\core;

use application\core\utils\Attach;
use application\core\utils\Env;
use application\core\utils\IBOS;
use application\core\utils\String;
use application\modules\message\model\Comment as CommentModel;
use application\modules\user\model\User;
use CWidget;

class Comment extends CWidget {

    const SOURCE_TABLE = 'Feed'; // 默认的资源表
    const REPLY_LIST_VIEW = 'application.modules.message.views.comment.loadReply'; // 默认的回复列表视图
    const COMMENT_LIST_VIEW = 'application.modules.message.views.comment.loadComment'; // 默认的评论列表视图
    const COMMENT_PARSE_VIEW = 'application.modules.message.views.comment.parseComment'; // 默认的单条评论视图
    const REPLY_PARSE_VIEW = 'application.modules.message.views.comment.parseReply'; // 默认的单条回复视图

    /**
     * 当前评论对象所指向的模块
     * @var string
     */

    private $_module;

    /**
     * 当前评论对象所指向的表名
     * @var string
     */
    private $_table;

    /**
     * 当前评论对象的其他属性
     * @var array
     */
    private $_attributes = array();

    /**
     * 解析视图时的默认视图
     * @var array
     */
    private $_views = array(
        'list' => array(
            'comment' => self::COMMENT_LIST_VIEW,
            'reply' => self::REPLY_LIST_VIEW,
        ),
        'parse' => array(
            'comment' => self::COMMENT_PARSE_VIEW,
            'reply' => self::REPLY_PARSE_VIEW
        )
    );

    /**
     * 设置当前评论widget所指向的模块名称
     * @param string $moduleName
     */
    public function setModule($moduleName = '') {
        $this->_module = String::filterCleanHtml($moduleName);
    }

    /**
     * 获得当前评论widget所指向的模块名称
     * @return string
     */
    public function getModule() {
        if ($this->_module !== null) {
            return $this->_module;
        } else {
            return IBOS::getCurrentModuleName();
        }
    }

    /**
     * 设置当前评论widget所指向的资源表名
     * @param string $tableName
     */
    public function setTable($tableName = '') {
        $this->_table = String::filterCleanHtml($tableName);
    }

    /**
     * 获得当前评论widget所指向的资源表名
     * @return string
     */
    public function getTable() {
        if ($this->_table !== null) {
            return $this->_table;
        } else {
            return self::SOURCE_TABLE;
        }
    }

    /**
     * 设置当前的评论widget的其他数据属性，详情请看comment表
     * @param array $attributes 键值对应的数组
     */
    public function setAttributes($attributes = array()) {
        foreach ($attributes as $key => $value) {
            $this->_attributes[$key] = $value;
        }
    }

    /**
     * 返回当前评论widget的其他属性
     * @param mixed 是否有指定详细的其他属性键
     * @return mixed 返回当个指定的键值或整个attributes
     */
    public function getAttributes($name = null) {
        if ($name !== null) {
            if (isset($this->_attributes[$name])) {
                return $this->_attributes[$name];
            } else {
                return null;
            }
        }
        return $this->_attributes;
    }

    /**
     * 设置渲染视图
     * @param string $type 视图类型
     * @param string $view 视图路径 (最好是全称)
     * @param string $index 视图的索引
     */
    public function setParseView($type = 'comment', $view = self::COMMENT_PARSE_VIEW, $index = 'list') {
        if (isset($this->_views[$index]) && isset($this->_views[$index][$type])) {
            $this->_views[$index][$type] = $view;
        }
    }

    /**
     * 获取解析视图
     * @param string $type 视图类型
     * @param string $index 视图索引
     * @return string
     */
    public function getParseView($type, $index = 'list') {
        if (isset($this->_views[$index]) && isset($this->_views[$index][$type])) {
            return $this->_views[$index][$type];
        }
    }

    /**
     *
     * @return integer
     */
    public function getCommentCount() {
        $map = $this->getCommentMap();
        return CommentModel::model()->countCommentByMap($map);
    }

    /**
     * 通用获取评论列表方法
     * @return array
     */
    public function getCommentList() {
        $map = $this->getCommentMap();
        // 分页形式数据
        $attr = $this->getAttributes();
        if (!isset($attr['limit'])) {
            $attr['limit'] = 10;
        }
        if (!isset($attr['offset'])) {
            $attr['offset'] = 0;
        }
        if (!isset($attr['order'])) {
            $attr['order'] = 'cid DESC';
        }
        $list = CommentModel::model()->getCommentList($map, $attr['order'], $attr['limit'], $attr['offset']);
        return $list;
    }

    /**
     * 通用添加评论方法
     * $data = array(
     * 		'uid','table','content','rowid',
     * 		'module','moduleuid'
     * )
     */
    public function addComment() {
        // 返回结果集默认值
        $return = array('isSuccess' => false, 'data' => IBOS::lang('Post comment fail', 'message'));
        // 获取接收数据
        $data = $_POST;
        $data['uid'] = IBOS::app()->user->uid;
        // 评论所属与评论内容
        $data['content'] = String::parseHtml( \CHtml::encode( \CHtml::encode( $data['content'] ) ) );
        $data['detail'] = isset($data['detail']) ? $data['detail'] : '';
        // 判断资源是否被删除
        if ($data['table'] == 'feed') {
            $table = 'application\modules\message\model\Feed';
        } else {
            $table = 'application\modules\\' . $data['module'] . '\\model\\' . ucfirst($data['table']);
        }
        $pk = $table::model()->getTableSchema()->primaryKey;
        $sourceInfo = $table::model()->fetch(array('condition' => "`{$pk}` = {$data['rowid']}"));
        if (!$sourceInfo) {
            $return['isSuccess'] = false;
            $return['data'] = IBOS::lang('Comment has been delete', 'message.default');
            $this->getOwner()->ajaxReturn($return);
        }
        $data['cid'] = CommentModel::model()->addComment($data);
        if (!empty($data['attachmentid'])) {
            Attach::updateAttach($data['attachmentid']);
        }
        $data['ctime'] = TIMESTAMP;
        if ($data['cid']) {
            $this->afterAdd($data, $sourceInfo);
            $return['isSuccess'] = true;
            $return['data'] = $this->parseComment($data);
        }
        $this->getOwner()->ajaxReturn($return);
    }

    /**
     * 删除评论
     * @return bool true or false
     */
    public function delComment() {
        $cid = intval(Env::getRequest('cid'));
        $comment = CommentModel::model()->getCommentInfo($cid);
        // 不存在时
        if (!$comment) {
            return false;
        }
        // 非作者时
        if ($comment ['uid'] != IBOS::app()->user->uid) {
            // 没有管理权限不可以删除
            if (!IBOS::app()->user->isadministrator) {
                $this->getOwner()->ajaxReturn(array('isSuccess' => false));
            }
        }
        if (!empty($cid)) {
            $this->beforeDelComment($comment, $cid);
            $res = CommentModel::model()->deleteComment($cid, IBOS::app()->user->uid);
            if ($res) {
                $this->getOwner()->ajaxReturn(array('isSuccess' => true));
            } else {
                $msg = CommentModel::model()->getError('deletecomment');
                $this->getOwner()->ajaxReturn(array('isSuccess' => false, 'msg' => $msg));
            }
        }
        $this->getOwner()->ajaxReturn(array('isSuccess' => false));
    }

    /**
     *
     * @return array
     */
    protected function getCommentMap() {
        $map = array('and');
        $rowid = $this->getAttributes('rowid');
        $map[] = sprintf("`module` = '%s'", $this->getModule());
        $map[] = sprintf("`table` = '%s'", $this->getTable());
        $map[] = '`rowid` = ' . intval($rowid); // 必须存在
        $map[] = '`isdel` = 0';
        return $map;
    }

    /**
     * 解析评论视图
     * @param array $data
     * @return string
     */
    protected function parseComment($data) {
        $uid = IBOS::app()->user->uid;
        $isAdministrator = IBOS::app()->user->isadministrator;
        $data ['userInfo'] = User::model()->fetchByUid($uid);
        $data['lang'] = IBOS::getLangSources(array('message.default'));
        $data['isCommentDel'] = $isAdministrator || $uid === $data['uid'];
        if (!empty($data['attachmentid'])) {
            $data['attach'] = Attach::getAttach($data['attachmentid']);
        }
        return $this->render($this->getParseView('comment', 'parse'), $data, true);
    }

    /**
     * 扩展方法，应由子widget来实现
     * @param array $data
     * @param array $sourceInfo
     */
    protected function afterAdd($data, $sourceInfo) {
        return false;
    }

    /**
     * 扩展方法，删除评论前预处理
     * @param array $comment 待删除的评论数组
     * @param integer $cid
     * @return boolean
     */
    protected function beforeDelComment($comment, &$cid) {
        if ($comment['table'] == 'comment') {
            $childId = CommentModel::model()->fetchReplyIdByCid($comment['cid']);
            $cid = array_merge(array($cid), $childId);
        }
    }

}
