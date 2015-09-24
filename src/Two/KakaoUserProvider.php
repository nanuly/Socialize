<?php

namespace Nanuly\Socialize\Two;

class KakaoProvider extends AbstractProvider implements ProviderInterface
{
	protected function getToken() 
	{
		$CLIENT_ID     = "[생성한 App의 REST API KEY를 입력하세요.]"; 
		$REDIRECT_URI  = "[App의 사이트 설정 정보의 REDIRECT URI를 입력하세요.]"; 
		$TOKEN_API_URL = "https://kauth.kakao.com/oauth/token"; 
		
		$code   = $_GET["code"]; 
		$params = sprintf( 'grant_type=authorization_code&client_id=%s&redirect_uri=%s&code=%s', $CLIENT_ID, $REDIRECT_URI, $code); 
		
		$opts = array( 
		CURLOPT_URL => $TOKEN_API_URL, 
		CURLOPT_SSL_VERIFYPEER => false, 
			CURLOPT_SSLVERSION => 1, // TLS
		CURLOPT_POST => true, 
		CURLOPT_POSTFIELDS => $params, 
		CURLOPT_RETURNTRANSFER => true, 
		CURLOPT_HEADER => false 
		); 
		
		$curlSession = curl_init(); 
		curl_setopt_array($curlSession, $opts); 
		$accessTokenJson = curl_exec($curlSession); 
		curl_close($curlSession); 
		
		$R_String= json_decode($accessTokenJson);
		
		$Expires_time = $R_String->{'expires_in'};///남은시간
		$access_token = $R_String->{'access_token'};///access_token
		
		if($Expires_time>0 && !empty($Expires_time)){
		
			$headers=array("Authorization:Bearer $access_token","Content-Type:application/x-www-form-urlencoded;charset=utf-8");
		
			$url="https://kapi.kakao.com/v1/user/me";
		
			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url);
			curl_setopt( $ch, CURLOPT_HTTPHEADER,$headers);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		
			$result = curl_exec($ch);
			$Member_Info= json_decode($result);
		
			curl_close($ch);
		
			//회원아이디 세션 생성
			$member = get_member($Member_Info->{'id'});
		
			if(empty($member[mb_id])){/////카카오톡 에 받은 고유키값을 비교 없으면 회원으로 등록시킨다.
				$mb_id=$Member_Info->{'id'};
				$mb_nick=$Member_Info->{'nickname'};
				$sql="insert into g5_member (mb_id,mb_password,mb_nick,mb_name)values('$mb_id',password('$mb_id'),'$mb_nick','$mb_nick')";
				mysql_query($sql);
			}
		
			set_session('ss_mb_id', $Member_Info->{'id'});
			set_session('ss_mb_key', md5($member['mb_datetime'] . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']));
			echo"<script>parent.opener.location.reload();parent.window.close();</script>";
		}
	}
	
}
