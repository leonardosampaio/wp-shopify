<?php

class Functions {

    private $configuration;

    public function __construct($configuration)
    {
        $this->configuration = $configuration;
    }

    private function getWpConnection()
    {
        $connection = mysqli_connect(
            $this->configuration->wordpressDbHost,
            $this->configuration->wordpressDbUser,
            $this->configuration->wordpressDbPassword,
            $this->configuration->wordpressDbName);

        if (!$connection)
        {
            echo 'Connection to Wordpress DB failed<br>';
            echo 'Error number: ' . mysqli_connect_errno() . '<br>';
            echo 'Error message: ' . mysqli_connect_error() . '<br>';
            die();    
        }
        
        return $connection;
    }

    public function getShopifyAccessToken($login)
    {
        $sql = "select m.meta_value ".
        "from ".$this->configuration->wordpressDbTablesPrefix."users u " .
        "inner join ".$this->configuration->wordpressDbTablesPrefix."usermeta m on u.ID = m.user_id ".
        "where m.meta_key = ? and u.user_login = ?";

        $mysqli = $this->getWpConnection();
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", 'accessToken', $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0)
        {
            return false;
        }

        $row = $result->fetch_assoc();

        $stmt->close();

        return isset($row['meta_value']) && $row['meta_value'] != ''? : null;
    }

    public function saveShopifyData($login, $shop, $accessToken)
    {
        $user = $this->getUser($login);

        foreach(['shop', 'accessToken'] as $metaKey)
        {
            $updateSql = "update ".$this->configuration->wordpressDbTablesPrefix."usermeta ".
                "set meta_value=? where meta_key=? and user_id = ?";

            if (!$user->hasAccessToken)
            {
                $updateSql = "insert into ".$this->configuration->wordpressDbTablesPrefix."usermeta (meta_value, meta_key, user_id) ".
                    "values (?, ?, ?)";
            }

            $mysqli = $this->getWpConnection();
            $stmt = $mysqli->prepare($updateSql);
            if (false===$stmt)
            {
                return false;
            }
            $stmt->bind_param("ssi", ${$metaKey}, $metaKey, $user->id);
            $rc = $stmt->execute();
            if (false===$rc)
            {
                return false;
            }
            $stmt->close();
        }

        return true;
    }
    
    public function getUser($login)
    {
        $sql = "select u.ID, ".
        "u.user_login, ".
        "case when m.meta_key is not null then 1 else 0 end as has_meta, " .
        "case when m_token.meta_key is not null then 1 else 0 end as has_access_token ".
        "from ".$this->configuration->wordpressDbTablesPrefix."users u " .
        "left join ".$this->configuration->wordpressDbTablesPrefix."usermeta m on u.ID = m.user_id and m.meta_key = ? ".
        "left join ".$this->configuration->wordpressDbTablesPrefix."usermeta m_token on u.ID = m_token.user_id and m_token.meta_key = ? ".
        "where u.user_login = ? limit 1";

        $accessTokenMetaKey = 'accessToken';

        $mysqli = $this->getWpConnection();
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("sss", $this->configuration->metaKey, $accessTokenMetaKey, $login);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0)
        {
            return false;
        }

        $row = $result->fetch_assoc();

        $user = new stdClass();
        $user->id = $row['ID'];
        $user->login = $row['user_login'];
        $user->hasMeta = $row['has_meta'] == 1;
        $user->hasAccessToken = $row['has_access_token'] == 1;

        $stmt->close();

        return $user;
    }

    public function updateMeta($user, $metaValue)
    {
        $mysqli = $this->getWpConnection();

        $updateSql = "update ".$this->configuration->wordpressDbTablesPrefix."usermeta ".
            "set meta_value=? where meta_key=? and user_id = ?";

        if (!$user->hasMeta)
        {
            $updateSql = "insert into ".$this->configuration->wordpressDbTablesPrefix."usermeta (meta_value, meta_key, user_id) ".
                "values (?, ?, ?)";
        }

        $stmt = $mysqli->prepare($updateSql);
        if (false===$stmt)
        {
            return false;
        }
        $stmt->bind_param("ssi", $metaValue, $this->configuration->metaKey, $user->id);
        $rc = $stmt->execute();
        if (false===$rc)
        {
            return false;
        }
        $stmt->close();

        return true;
    }

    public function getAllUserMeta($login)
    {
        $mysqli = $this->getWpConnection();

        $sql = "select m.* from ".$this->configuration->wordpressDbTablesPrefix."usermeta m " .
            "inner join ".$this->configuration->wordpressDbTablesPrefix."users u on u.ID = m.user_id ".
            "where u.user_login = ? order by umeta_id desc";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("s", $login);
        $stmt->execute();
        $stmtResult = $stmt->get_result();

        if ($stmtResult->num_rows === 0)
        {
            return false;
        }

        $result = [];

        while($row = $stmtResult->fetch_assoc()) {
            $result[] = $row;
        }

        $stmt->close();

        return $result;
    }

    public function getMetaKeyValue($login, $metaKey)
    {
        $mysqli = $this->getWpConnection();

        $sql = "select m.* from ".$this->configuration->wordpressDbTablesPrefix."usermeta m " .
            "inner join ".$this->configuration->wordpressDbTablesPrefix."users u on u.ID = m.user_id ".
            "where u.user_login = ? and m.meta_key = ? order by umeta_id desc limit 1";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $login, $metaKey);
        $stmt->execute();
        $stmtResult = $stmt->get_result();

        if ($stmtResult->num_rows === 0)
        {
            return false;
        }

        $result = false;

        while($row = $stmtResult->fetch_assoc()) {
            $result = $row;
        }

        $stmt->close();

        return $result['meta_value'];
    }

    /**
     * https://github.com/Trifoia/Basic-Auth
     */
    public function doWpJsonAuth($username, $password, $url)
    {
        $consumer = curl_init();

        curl_setopt($consumer, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($consumer, CURLOPT_USERPWD, $username . ":" . $password);

        curl_setopt($consumer, CURLOPT_URL, $url);
        curl_setopt($consumer, CURLOPT_PORT, (strpos($url,'https://')!==false ? 443 : 80));
        curl_setopt($consumer, CURLOPT_FOLLOWLOCATION, true);
        
        curl_setopt($consumer, CURLOPT_HEADER, 0);
        curl_setopt($consumer, CURLOPT_POST, 0); 
        curl_setopt($consumer, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($consumer, CURLOPT_SSL_VERIFYPEER, 1);

        return [
            'response'=>curl_exec($consumer),
            'httpCode'=>curl_getinfo($consumer, CURLINFO_HTTP_CODE)];
    }
}