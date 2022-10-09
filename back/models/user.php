<?php
require_once __DIR__ . '/../utils/database.php';
require_once __DIR__ . '/../utils/token.php';
require_once __DIR__ . '/../utils/filesUpload.php';
class User
{
    private $dataBase;
    private $table = 'User';
    private $token;
    private $fileUploader;
    // private $baseUrl = 'http://localhost:4200/back';
    private $baseUrl = 'http://stand2.progoff.ru/back';

    // конструктор класса User
    public function __construct(DataBase $dataBase)
    {
        $this->dataBase = $dataBase;
        $this->token = new Token();
        $this->fileUploader = new FilesUpload();
    }

    // public function create($userData)
    // {
    //     $userData = (object) $this->dataBase->stripAll((array)$userData);

    //     // Вставляем запрос
    //     $userData->password = password_hash($userData->password, PASSWORD_BCRYPT);

    //     $query = $this->dataBase->genInsertQuery(
    //         $userData,
    //         $this->table
    //     );

    //     // подготовка запроса
    //     $stmt = $this->dataBase->db->prepare($query[0]);
    //     if ($query[1][0] != null) {
    //         $stmt->execute($query[1]);
    //     }
    //     $userId = $this->dataBase->db->lastInsertId();
    //     if ($userId) {
    //         $tokens = $this->token->encode(array("id" => $userId));
    //         $this->addRefreshToken($tokens["refreshToken"], $userId);
    //         return $tokens;
    //     }
    //     return null;
    // }

    public function readContent()
    {
        $query = "SELECT * FROM Content";
        $content = $this->dataBase->db->query($query)->fetchAll();
        return $content;
    }

    public function updateContent($data, $imgs)
    {
        $content = $this->readContent();
        if ($data != null) {
            foreach (array_keys($data) as $contentId) {
                $query = [];
                if (array_search($contentId, array_column($content, 'id'))) {
                    $query = $this->dataBase->genUpdateQuery(
                        array("value" => $data[$contentId]),
                        "Content",
                        $contentId
                    );
                } else {
                    $query = $this->dataBase->genInsertQuery(
                        array("value" => $data[$contentId]),
                        "Content"
                    );
                }

                $stmt = $this->dataBase->db->prepare($query[0]);
                if ($query[1][0] != null) {
                    $stmt->execute($query[1]);
                }
            }
        }

        $data = $data == null ? [] : $data;


        $this->setPhotos($content, $imgs, $data);


        return $data;
    }

    public function login($password)
    {
        if ($password != null) {
            $sth = $this->dataBase->db->prepare("SELECT id, password FROM " . $this->table . " LIMIT 1");
            $sth->execute(array());
            $fullUser = $sth->fetch();
            if ($fullUser) {
                if (!password_verify($password, $fullUser['password'])) {
                    throw new Exception("User not found", 404);
                }
                $tokens = $this->token->encode(array("id" => $fullUser['id']));
                $this->addRefreshToken($tokens["refreshToken"], $fullUser['id']);
                return $tokens;
            } else {
                throw new Exception("Неверный пароль", 404);
            }
        } else {
            throw new Exception("Введите пароль", 409);
        }
    }

    public function isRefreshTokenActual($token, $userId)
    {
        $query = "SELECT id FROM RefreshTokens WHERE token = ? AND userId = ?";

        // подготовка запроса
        $stmt = $this->dataBase->db->prepare($query);
        // инъекция
        $token = htmlspecialchars(strip_tags($token));
        $userId = htmlspecialchars(strip_tags($userId));
        // выполняем запрос
        $stmt->execute(array($token, $userId));

        // получаем количество строк
        $num = $stmt->rowCount();

        if ($num > 0) {
            return true;
        }

        return $num > 0;
    }

    public function addRefreshToken($tokenn, $userId)
    {
        $query = "DELETE FROM RefreshTokens WHERE userId=$userId";
        $this->dataBase->db->query($query);
        $query = "INSERT INTO RefreshTokens (token, userId) VALUES ('$tokenn', $userId)";
        $this->dataBase->db->query($query);
    }

    public function removeRefreshToken($token)
    {
        $userId = $this->token->decode($token, true)->data->id;
        $query = "DELETE FROM RefreshTokens WHERE userId = $userId";
        $this->dataBase->db->query($query);
    }

    public function refreshToken($token)
    {
        $userId = $this->token->decode($token, true)->data->id;

        if (!$this->isRefreshTokenActual($token, $userId)) {
            throw new Exception("Unauthorized", 401);
        }

        $this->removeRefreshToken($userId);

        $tokens = $this->token->encode(array("id" => $userId));
        $this->addRefreshToken($tokens[1], $userId);
        return $tokens;
    }

    private function setPhotos($content, $photos, &$result)
    {
        $photoIds = array_keys($photos);
        if ($photoIds == null || count($photoIds) < 1) {
            return;
        }

        $this->unsetPhotos($photoIds);

        foreach ($photoIds as $contentId) {
            $res = $this->fileUploader->upload($photos[$contentId], 'Images', uniqid(), $this->baseUrl);
            $query = [];
            if (array_search($contentId, array_column($content, 'id'))) {
                $query = $this->dataBase->genUpdateQuery(
                    array("value" => $res),
                    "Content",
                    $contentId
                );
            } else {
                $query = $this->dataBase->genInsertQuery(
                    array("id" => $contentId, "value" => $res),
                    "Content"
                );
            }

            $stmt = $this->dataBase->db->prepare($query[0]);
            if ($query[1][0] != null) {
                $stmt->execute($query[1]);
            }
            $result[$contentId] = $res;
        }

        return $result;
    }

    private function unsetPhotos($ids)
    {
        $ids = implode(", ", $ids);
        $stmt = $this->dataBase->db->query("select value from Content where id IN ($ids)");

        while ($url = $stmt->fetch()) {
            $this->fileUploader->removeFile($url['value'], $this->baseUrl);
        }

        return true;
    }
}
