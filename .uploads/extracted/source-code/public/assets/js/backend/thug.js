define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'thug/index' + location.search,
                    add_url: 'thug/add',
                    edit_url: 'thug/edit',
                    //del_url: 'thug/del',
                    multi_url: 'thug/multi',
                    import_url: 'thug/import',
                    table: 'thug',
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
                        //{field: 'user_id', title: __('User_id')},
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'avatar', title: __('Avatar'), operate: 'LIKE', events: Table.api.events.image, formatter: Table.api.formatter.image},
                        {field: 'sex', title: __('Sex'), searchList: {"0":__('Sex 0'),"1":__('Sex 1')}, formatter: Table.api.formatter.normal},
                        //{field: 'audio', title: __('Audio'), operate: 'LIKE'},
                        //{field: 'images', title: __('Images'), operate: false, events: Table.api.events.image, formatter: Table.api.formatter.images},
                        {field: 'synopsis', title: __('Synopsis'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'grade', title: __('Grade'), operate:'BETWEEN'},
                        {field: 'type', title: __('Type'), searchList: {"1":__('Type 1'),"2":__('Type 2'),"3":__('Type 3')}, formatter: Table.api.formatter.normal},
                        {field: 'total_settled', title: __('累计结算'), operate:'BETWEEN'},
                        {field: 'available_money', title: __('可用佣金'), operate:'BETWEEN'},
                        {field: 'frozen_money', title: __('冻结佣金'), operate:'BETWEEN'},
                        {field: 'month_settled', title: __('本月结算'), operate:'BETWEEN'},
                        {field: 'last_month_settled', title: __('上月结算'), operate:'BETWEEN'},
                        {field: 'bond', title: __('保证金'), operate:'BETWEEN'},
                        {field: 'recommend', title: __('推荐'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.toggle},
                        {field: 'is_customer_service', title: __('客服身份'), searchList: {"0":__('否'),"1":__('是')}, formatter: Table.api.formatter.toggle},
                        {field: 'jd_status', title: __('Jd_status'), searchList: {"0":__('Jd_status 0'),"1":__('Jd_status 1')}, formatter: Table.api.formatter.toggle},
                        {field: 'sh_status', title: __('Sh_status'), searchList: {"0":__('Sh_status 0'),"1":__('Sh_status 1'),"2":__('Sh_status 2')}, formatter: Table.api.formatter.status},
                        //{field: 'remarks', title: __('Remarks'), operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'createtime', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
