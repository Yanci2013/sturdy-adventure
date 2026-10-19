define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'peiwan/withdraw_audit/index' + location.search,
                    detail_url: 'peiwan/withdraw_audit/detail',
                    approve_url: 'peiwan/withdraw_audit/approve',
                    reject_url: 'peiwan/withdraw_audit/reject',
                    table: 'withdraw_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'user_nickname', title: '用户', operate: false, formatter: function(value, row) {
                            var avatar = row.user_avatar || '/assets/img/avatar.png';
                            return '<img src="' + avatar + '" style="width:24px;height:24px;border-radius:50%;margin-right:5px;">' + (value || '-');
                        }},
                        {field: 'amount', title: '提现金额', operate: 'BETWEEN', formatter: function(value) {
                            return '<span style="color:#f00;font-weight:bold;">¥' + value + '</span>';
                        }},
                        {field: 'real_name', title: '真实姓名'},
                        {field: 'qrcode_image', title: '收款二维码', operate: false, events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'status', title: '状态', searchList: {
                            "audit_pending": "待审核",
                            "success": "成功",
                            "rejected": "已拒绝"
                        }, formatter: Table.api.formatter.status},
                        {field: 'remark', title: '备注', operate: false},
                        {field: 'createtime', title: '申请时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'audit_time_text', title: '审核时间', operate: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function(value, row, index) {
                            var that = $.extend({}, this);
                            var table = $(that.table).clone(true);
                            
                            // 只有待审核状态显示审核按钮
                            if (row.status === 'audit_pending') {
                                return '<a href="javascript:;" class="btn btn-xs btn-success btn-approve" data-id="' + row.id + '" title="审核通过"><i class="fa fa-check"></i></a> ' +
                                       '<a href="javascript:;" class="btn btn-xs btn-danger btn-reject" data-id="' + row.id + '" title="审核拒绝"><i class="fa fa-times"></i></a> ' +
                                       '<a href="javascript:;" class="btn btn-xs btn-info btn-detail" data-id="' + row.id + '" title="查看详情"><i class="fa fa-eye"></i></a>';
                            } else {
                                return '<a href="javascript:;" class="btn btn-xs btn-info btn-detail" data-id="' + row.id + '" title="查看详情"><i class="fa fa-eye"></i></a>';
                            }
                        }}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            
            // 审核通过按钮事件
            $(document).on('click', '.btn-approve', function() {
                var id = $(this).data('id') || Table.api.selectedids(table).join(',');
                if (!id) {
                    Layer.msg('请选择要审核的记录');
                    return;
                }
                Layer.confirm('确定审核通过吗？通过后请手动转账给用户。', function() {
                    $.ajax({
                        url: 'peiwan/withdraw_audit/approve',
                        type: 'POST',
                        data: {ids: id},
                        success: function(res) {
                            if (res.code === 1) {
                                Layer.msg(res.msg, {icon: 1});
                                table.bootstrapTable('refresh');
                            } else {
                                Layer.msg(res.msg, {icon: 2});
                            }
                        }
                    });
                });
            });
            
            // 审核拒绝按钮事件
            $(document).on('click', '.btn-reject', function() {
                var id = $(this).data('id') || Table.api.selectedids(table).join(',');
                if (!id) {
                    Layer.msg('请选择要拒绝的记录');
                    return;
                }
                Layer.prompt({title: '请输入拒绝原因', formType: 2}, function(reason, index) {
                    $.ajax({
                        url: 'peiwan/withdraw_audit/reject',
                        type: 'POST',
                        data: {ids: id, reason: reason},
                        success: function(res) {
                            if (res.code === 1) {
                                Layer.msg(res.msg, {icon: 1});
                                table.bootstrapTable('refresh');
                            } else {
                                Layer.msg(res.msg, {icon: 2});
                            }
                        }
                    });
                    Layer.close(index);
                });
            });
            
            // 查看详情按钮事件
            $(document).on('click', '.btn-detail', function() {
                var id = $(this).data('id');
                Fast.api.open('peiwan/withdraw_audit/detail?ids=' + id, '提现详情', {
                    area: ['800px', '600px']
                });
            });
            
            // 工具栏按钮状态控制
            table.on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function () {
                var ids = Table.api.selectedids(table);
                $('.btn-approve,.btn-reject').toggleClass('disabled btn-disabled', !ids.length);
            });
        },
        detail: function () {
            // 审核通过
            $(document).on('click', '.btn-approve', function() {
                var id = $(this).data('id');
                Layer.confirm('确定审核通过吗？通过后请手动转账给用户。', function() {
                    $.ajax({
                        url: 'peiwan/withdraw_audit/approve',
                        type: 'POST',
                        data: {ids: id},
                        success: function(res) {
                            if (res.code === 1) {
                                Layer.msg(res.msg, {icon: 1});
                                setTimeout(function() {
                                    parent.location.reload();
                                }, 1000);
                            } else {
                                Layer.msg(res.msg, {icon: 2});
                            }
                        }
                    });
                });
            });
            
            // 审核拒绝
            $(document).on('click', '.btn-reject', function() {
                var id = $(this).data('id');
                Layer.prompt({title: '请输入拒绝原因', formType: 2}, function(reason, index) {
                    $.ajax({
                        url: 'peiwan/withdraw_audit/reject',
                        type: 'POST',
                        data: {ids: id, reason: reason},
                        success: function(res) {
                            if (res.code === 1) {
                                Layer.msg(res.msg, {icon: 1});
                                setTimeout(function() {
                                    parent.location.reload();
                                }, 1000);
                            } else {
                                Layer.msg(res.msg, {icon: 2});
                            }
                        }
                    });
                    Layer.close(index);
                });
            });
        }
    };
    return Controller;
});
