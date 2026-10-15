define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'upload'], function ($, undefined, Backend, Table, Form, Upload) {

    // 权益管理公共方法
    function initRightsEditor() {
        // 添加权益行
        function addRightRow(item) {
            item = item || {title: '', icon: '', desc: ''};
            var idx = $('#rights-list .right-row').length;
            var html = '<div class="right-row" style="border:1px solid #ddd;padding:12px;margin-bottom:10px;border-radius:6px;background:#fafafa;position:relative;">'
                + '<div style="margin-bottom:8px;">'
                + '<label style="font-weight:600;display:block;margin-bottom:4px;">权益名称</label>'
                + '<input type="text" class="form-control right-title" value="' + (item.title || '').replace(/"/g, '&quot;') + '" placeholder="如：专属客服">'
                + '</div>'
                + '<div style="margin-bottom:8px;">'
                + '<label style="font-weight:600;display:block;margin-bottom:4px;">图标图片</label>'
                + '<div class="input-group">'
                + '<input type="text" class="form-control right-icon" id="right-icon-' + idx + '" value="' + (item.icon || '').replace(/"/g, '&quot;') + '" placeholder="上传或粘贴图片地址">'
                + '<div class="input-group-btn">'
                + '<button type="button" class="btn btn-danger btn-right-upload" data-idx="' + idx + '"><i class="fa fa-upload"></i> 上传</button>'
                + '</div>'
                + '</div>'
                + '</div>'
                + '<div style="margin-bottom:8px;">'
                + '<label style="font-weight:600;display:block;margin-bottom:4px;">描述（选填）</label>'
                + '<input type="text" class="form-control right-desc" value="' + (item.desc || '').replace(/"/g, '&quot;') + '" placeholder="权益描述">'
                + '</div>'
                + '<button type="button" class="btn btn-danger btn-xs btn-remove-right" style="position:absolute;top:8px;right:8px;"><i class="fa fa-times"></i></button>'
                + '</div>';
            $('#rights-list').append(html);
        }

        // 点击添加
        $(document).off('click.addright').on('click.addright', '#btn-add-right', function () {
            addRightRow();
        });

        // 点击删除
        $(document).off('click.removeright').on('click.removeright', '.btn-remove-right', function () {
            $(this).closest('.right-row').remove();
        });

        // 点击上传图标
        $(document).off('click.uploadright').on('click.uploadright', '.btn-right-upload', function () {
            var $btn = $(this);
            var $row = $btn.closest('.right-row');
            var $input = $row.find('.right-icon');
            // 使用 FastAdmin 的选择附件功能
            parent.Fast.api.open("general/attachment/select?mimetype=image/*&multiple=false", "选择图片", {
                callback: function (data) {
                    $input.val(data.url);
                }
            });
        });

        // 加载已有数据
        var existingVal = $('#c-rights').val();
        if (existingVal) {
            try {
                var list = JSON.parse(existingVal);
                if (Array.isArray(list)) {
                    for (var i = 0; i < list.length; i++) {
                        addRightRow(list[i]);
                    }
                }
            } catch (e) {
            }
        }
    }

    // 序列化权益到隐藏字段
    function serializeRights() {
        var rights = [];
        $('#rights-list .right-row').each(function () {
            var title = $(this).find('.right-title').val().trim();
            var icon = $(this).find('.right-icon').val().trim();
            var desc = $(this).find('.right-desc').val().trim();
            if (title) {
                rights.push({title: title, icon: icon, desc: desc});
            }
        });
        $('#c-rights').val(rights.length ? JSON.stringify(rights) : '');
    }

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'level/index' + location.search,
                    add_url: 'level/add',
                    edit_url: 'level/edit',
                    del_url: 'level/del',
                    multi_url: 'level/multi',
                    import_url: 'level/import',
                    table: 'level',
                }
            });

            var table = $("#table");

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'sort',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'name', title: '等级名称', operate: 'LIKE'},
                        {field: 'cover', title: '封面图', events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        {field: 'score', title: '成长值'},
                        {field: 'price', title: '价格(元)', operate: false},
                        {field: 'days', title: '有效天数', operate: false},
                        {field: 'content', title: '权益说明', operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'sort', title: '排序'},
                        {field: 'thug_visible', title: '打手可见', formatter: function(value){return value == 1 ? '<span class="label label-success">是</span>' : '<span class="label label-default">否</span>';}},
                        {field: 'createtime', title: '创建时间', operate: 'RANGE', addclass: 'datetimerange', autocomplete: false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: '操作', table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
            initRightsEditor();
        },
        edit: function () {
            Controller.api.bindevent();
            initRightsEditor();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"), null, null, function () {
                    serializeRights();
                    return true;
                });
            }
        }
    };
    return Controller;
});
