/**
 * common demo
 */
 
layui.define(function(exports){
  var $ = layui.$
  ,layer = layui.layer
  ,laytpl = layui.laytpl
  ,setter = layui.setter
  ,view = layui.view
  ,admin = layui.admin
  
  //公共业务的逻辑处理可以写在此处，切换任何页面都会执行
  //……
  
  
  
  //退出
  admin.events.logout = function(){
    layer.confirm('确定要退出登录吗？', {
      icon: 3,
      title: '提示'
    }, function(index){
      layer.close(index);
      location.href = 'logout.php';
    });
  };

  
  //对外暴露的接口
  exports('common', {});
});