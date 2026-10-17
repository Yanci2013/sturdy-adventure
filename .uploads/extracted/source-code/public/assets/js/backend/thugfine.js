define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'thugfine/index' + location.search,
                    add_url: 'thugfine/add',
                    table: 'thug_fine',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'fine_no', title: '罚单编号', operate: 'LIKE'},
                        {field: 'thug.name', title: '打手姓名', operate: 'LIKE'},
                        {field: 'thug.mobile', title: '打手手机', operate: 'LIKE'},
                        {field: 'order.order_no', title: '关联订单', operate: 'LIKE'},
                        {field: 'amount', title: '罚款金额', operate: 'BETWEEN'},
                        {field: 'reason', title: '罚款理由', operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {
                            field: 'punish_type',
                            title: '处罚类型',
                            searchList: {"1": "禁止接单+禁止提现", "2": "可正常接单但禁止提现"},
                            formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'target_type',
                            title: '目标类型',
                            searchList: {"1": "全部打手", "2": "指定打手"},
                            formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'status',
                            title: '状态',
                            searchList: {"0": "待缴纳", "1": "已缴纳", "2": "已撤销"},
                            formatter: Table.api.formatter.status
                        },
                        {field: 'fine_time', title: '罚款时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
                        {field: 'pay_time', title: '缴纳时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
                        {field: 'createtime', title: '创建时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
                        {
                            field: 'operate',
                            title: '操作',
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'revoke',
                                    text: '撤销',
                                    title: '撤销罚单',
                                    classname: 'btn btn-xs btn-warning btn-ajax',
                                    icon: 'fa fa-undo',
                                    url: 'thugfine/revoke',
                                    confirm: '确认撤销该罚单吗？',
                                    success: function () {
                                        table.bootstrapTable('refresh');
                                    },
                                    visible: function (row) {
                                        return row.status == 0;
                                    }
                                }
                            ]
                        }
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            function toggleThug(){
                var val = $('input[name="row[target_type]"]:checked').val();
                if(val == '1'){
                    $('#thug-group').hide();
                } else {
                    $('#thug-group').show();
                }
            }
            toggleThug();
            $(document).on('change', 'input[name="row[target_type]"]', toggleThug);
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
