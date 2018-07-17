<?php

namespace App\Service\Admin;

use App\Repositories\Eloquent\ApplicationRepositoryEloquent;
use App\Service\Admin\BaseService;
use Exception;
use DB;

/**
 * ApplicationService Service
 */
class ApplicationService extends BaseService
{

    protected $applicationRepository;

    function __construct(
        ApplicationRepositoryEloquent $applicationRepository
    )
    {
        $this->applicationRepository = $applicationRepository;
    }

    /**
     * datatables获取数据
     */
    public function ajaxIndex()
    {
        // datatables请求次数
        $draw = request('draw', 1);
        // 开始条数
        $start = request('start', config('admin.golbal.list.start'));
        // 每页显示数目
        $length = request('length', config('admin.golbal.list.length'));
        // datatables是否启用模糊搜索
        $search['regex'] = request('search.regex', false);
        // 搜索框中的值
        $search['value'] = request('search.value', '');
        // 排序
        $order['name'] = request('columns.' .request('order.0.column',0) . '.name');
        $order['dir'] = request('order.0.dir','asc');

        $result = $this->applicationRepository->getApplicationList($start,$length,$search,$order);

        $apps = [];

        if ( $result['apps'] ) {
            foreach ( $result['apps'] as $v ) {
                $v->actionButton = $v->getActionButtonAttribute();
                $apps[] = $v;
            }
        }

        return [
            'draw' => $draw,
            'recordsTotal' => $result['count'],
            'recordsFiltered' => $result['count'],
            'data' => $apps,
        ];
    }

    /**
     * 添加应用
     */
    public function storeApplication( $attributes )
    {
        try {
            $result = $this->applicationRepository->create( $attributes );
            flash_info( $result, trans('admin/alert.common.create_success'), trans('admin/alert.common.create_error') );

            return [
                'status' => $result,
                'message' => $result ? trans('admin/alert.common.create_success'):trans('admin/alert.common.create_error'),
            ];
        } catch (Exception $e) {
            // 错误信息发送邮件
            $this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
            return false;
        }
    }

    /**
     * 根据ID查找数据
     */
    public function findApplicationById( $id )
    {
        $application = $this->applicationRepository->find( $id );
        /* 查找 language 数据 */
        if ( $application )
        {
            return $application;
        }

        abort(404);
    }

    /**
     * 修改数据
     */
    public function updateProject( $attributes, $id )
    {
        // 防止用户恶意修改表单id，如果id不一致直接跳转500
        if ( $attributes['id'] != $id )
        {
            return [
                'status' => false,
                'message' => trans('admin/errors.user_error'),
            ];
        }
        try {
            DB::beginTransaction();
            /* 关联子表操作：存储多语言 */
            $this->_storeLanguage( $attributes['languages'], $id );
            $attributes['languages'] = implode( ',', $attributes['languages'] );
            $attributes['user_id']   = getUser()->id;
            $attributes['username']  = getUser()->username;
            $isUpdate = $this->project->update( $attributes, $id );
            $this->translatorRepository->updateProjectName( $attributes['name'], $id );
            DB::commit();

            return [
                'status' => $isUpdate,
                'message' => $isUpdate ? trans('admin/alert.project.edit_success'):trans('admin/alert.project.edit_error'),
            ];
        } catch (Exception $e) {
            var_dump( $e->getMessage() );die;
            DB::rollBack();
            // 错误信息发送邮件
            $this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
            return false;
        }
    }

    /**
     * 存储 多语言
     * @author Yusure  http://yusure.cn
     * @date   2017-11-08
     * @param  [param]
     * @return [type]     [description]
     */
    private function _storeLanguage( $languages, $id )
    {
        /* 查找旧的 language 用来作比对 */
        $old_languages = $this->languageRepository->getOldLanguage( $id );

        /* 删除其他未选中的语言 */
        $this->languageRepository->deleteOtherLanguage( $languages, $id );

        /* foreach 新的 languages 判断是否存在，不存在就写入 */
        foreach ( $languages as $k => $language )
        {
            $result = array_search( $language, $old_languages );
            if ( false === $result )
            {
                /* 写入 languages */
                $data = [
                    'project_id' => $id,
                    'language'   => $language,
                    'status'     => 1,
                ];
                $this->languageRepository->insert( $data );
            }
        }
    }

    /**
     * 删除
     */
    public function destroyProject( $id )
    {
        try {
            $isDestroy = $this->project->delete( $id );
            flash_info($isDestroy,trans('admin/alert.project.destroy_success'),trans('admin/alert.project.destroy_error'));
            return $isDestroy;
        } catch (Exception $e) {
            // 错误信息发送邮件
            $this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
            return false;
        }
    }

    public function orderable($nestableData)
    {
        try {
            $dataArray = json_decode($nestableData,true);
            $bool = false;
            DB::beginTransaction();
            foreach ($dataArray as $k => $v) {
                $this->project->update(['sort' => $v['sort']],$v['id']);
                $bool = true;
            }
            DB::commit();
            if ($bool) {
                // 更新缓存
                $this->getProjectSetCache();
            }
            return [
                'status' => $bool,
                'message' => $bool ? trans('admin/alert.project.order_success'):trans('admin/alert.project.order_error')
            ];
        } catch (Exception $e) {
            // 错误信息发送邮件
            DB::rollBack();
            $this->sendSystemErrorMail(env('MAIL_SYSTEMERROR',''),$e);
            return false;
        }
    }
}