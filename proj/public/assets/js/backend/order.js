define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/index' + location.search,
                    add_url: 'order/add',
                    edit_url: 'order/edit',
                    //del_url: 'order/del',
                    multi_url: 'order/multi',
                    import_url: 'order/import',
                    table: 'order',
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
                        {field: 'order_no', title: __('订单号'), operate: 'LIKE'},
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: {
                                "0": __('Status 0'),
                                "1": __('Status 1'),
                                "2": __('Status 2'),
                                "3": __('Status 3'),
                                "4": __('Status 4'),
                                "5": __('Status 5'),
                                "6": __('Status 6'),
                                "7": __('Status 7'),
                                "8": __('Status 8')
                            },
                            formatter: Table.api.formatter.status
                        },
                        {field: 'wz_id', title: __('游戏昵称'), operate: 'LIKE'},
                        {field: 'game_id', title: __('ID编号'), operate: 'LIKE'},
                        {
                            field: 'type',
                            title: __('Type'),
                            searchList: {"1": __('Type 1'), "2": __('Type 2')},
                            formatter: Table.api.formatter.normal
                        },
                        {field: 'goods_types', title: __('游戏类型'), operate: false},
                        {
                            field: 'goods_type',
                            title: __('游戏类型'),
                            visible: false,
                            searchList: $.getJSON("goods/goods/onetype"),
                            formatter: Table.api.formatter.status,
                            operate: 'LIKE'
                        },
                        {field: 'game_qf', title: __('Game_qf'), operate: 'LIKE'},
                        {field: 'goods.title', title: __('Goods.title'), operate: 'LIKE'},
                        {
                            field: 'goods.logo',
                            title: __('Goods.logo'),
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image,
                            operate: false,
                        },
                        {field: 'jackpot.name', title: __('奖品'), operate: 'LIKE'},
                        {
                            field: 'jackpot.image',
                            title: __('奖品图片'),
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image,
                            operate: false,
                        },
                        {field: 'price', title: __('Price'), operate: 'BETWEEN'},
                        {field: 'number', title: __('Number'), operate: false},
                        {field: 'pay_price', title: __('支付金额'), operate: 'BETWEEN'},
                        {field: 'received_price', title: __('打手到手价'), operate: 'BETWEEN'},
                        {field: 'profit', title: __('利润'), operate: false},
                        {field: 'thug.name', title: __('Thug.name'), operate: 'LIKE'},
                        {field: 'thug.mobile', title: __('打手手机号'), operate: 'LIKE'},
                        {field: 'helper', title: __('队友'), operate: false},
                        {
                            field: 'remarks',
                            title: __('Remarks'),
                            operate: 'LIKE',
                            table: table,
                            class: 'autocontent',
                            formatter: Table.api.formatter.content
                        },
                        {
                            field: 'connecttime',
                            title: __('Connecttime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'completetime',
                            title: __('Completetime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'image',
                            title: __('Image'),
                            operate: false,
                            events: Table.api.events.image,
                            formatter: Table.api.formatter.image
                        },
                        {field: 'user.nickname', title: __('User.nickname'), operate: 'LIKE'},
                        {field: 'user.mobile', title: __('用户手机号'), operate: 'LIKE'},
                        {
                            field: 'createtime',
                            title: __('Createtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'paytime',
                            title: __('Paytime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'confirmtime',
                            title: __('Confirmtime'),
                            operate: 'RANGE',
                            addclass: 'datetimerange',
                            autocomplete: false,
                            formatter: Table.api.formatter.datetime
                        },
                        {
                            field: 'operate',
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'edit',
                                    text: __('订单详情'),
                                    title: function (row) {
                                        return '订单详情'
                                    },
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-list-alt',
                                    url: function (row) {
                                        return 'order/edit?ids=' + row.id
                                    },
                                    callback: function (data) {
                                    },
                                    visible: function (row) {
                                        //返回true时按钮显示,返回false隐藏
                                        return true;
                                    }
                                },
                                {
                                    name: 'ds',
                                    text: __('选择打手'),
                                    title: function (row) {
                                        return '打手列表'
                                    },
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-list-alt',
                                    url: function (row) {
                                        return 'order/thug?ids=' + row.id
                                    },
                                    callback: function (data) {
                                    },
                                    visible: function (row) {
                                        if (row.status == 1 || row.status == 2 || row.status == 3 ) {
                                            return true;
                                        }
                                        return false;
                                    }
                                },
                                {
                                    name: 'qx',
                                    text: __('取消订单'),
                                    title: __('取消订单'),
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-list-alt',
                                    url: 'order/cancel',
                                    confirm: "确认取消订单吗？",
                                    callback: function (data) {
                                        table.bootstrapTable('refresh');
                                        // Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    },
                                    visible: function (row) {
                                        if (row.status == 0) {
                                            return true;
                                        }
                                        return false;
                                    },
                                    success: function () {
                                        table.bootstrapTable('refresh');
                                    }
                                },
                                {
                                    name: 'qr',
                                    text: __('确认订单'),
                                    title: __('确认订单'),
                                    classname: 'btn btn-xs btn-success btn-ajax',
                                    icon: 'fa fa-list-alt',
                                    url: 'order/confirm',
                                    confirm: "确认订单吗？",
                                    callback: function (data) {
                                        table.bootstrapTable('refresh');
                                        // Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    },
                                    visible: function (row) {
                                        if (row.status == 4  && row.confirmtime == 0) {
                                            return true;
                                        }
                                        return false;
                                    },
                                    success: function () {
                                        table.bootstrapTable('refresh');
                                    }
                                },
                                {
                                    name: 'fine',
                                    text: __('发罚单'),
                                    title: function (row) {
                                        return '发罚单 - 订单#' + row.id;
                                    },
                                    classname: 'btn btn-xs btn-danger btn-dialog',
                                    icon: 'fa fa-gavel',
                                    url: function (row) {
                                        return 'thugfine/add?order_id=' + row.id + '&thug_id=' + (row.thug_id || 0);
                                    },
                                    callback: function (data) {
                                        table.bootstrapTable('refresh');
                                    },
                                    visible: function (row) {
                                        // 有打手的订单才显示发罚单按钮
                                        return row.thug_id > 0;
                                    }
                                },
                            ]
                        },
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
        thug: function () {
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
