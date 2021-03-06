<?php

namespace model;

require_once dirname(__FILE__) . '/../model_exceptions/UnableToGetTOException.php';

use exceptions\UnableToGetTOException;

class MysqliUsersDAO extends MysqliDAO implements ISyncDAO, IUsersDAO
{

    /**
     * @param $userTO UserTO
     */
    function syncTO($userTO) {
        static::$link->begin_transaction();
        $phone = $userTO->getPhone();
        $name = $userTO->getName();
        $picture = $userTO->getPictureId();
        $id = $userTO->getId();
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $stmt = static::$link->prepare('UPDATE Users SET phone=?,name=?,picture_id=?,last_update=? WHERE _id=?');
        $stmt->bind_param('ssisi', $phone, $name, $picture, $now, $id);
        $stmt->execute();
        $stmt->fetch();
        $stmt->close();

        // Clear blocked users
        $stmt = static::$link->prepare('DELETE FROM Blocked WHERE blocker=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        $stmt = static::$link->prepare('INSERT INTO Blocked (blocker, blocked)
                                        VALUES (?,?)
                                        ON DUPLICATE KEY UPDATE blocker=?');
        $stmt->bind_param('iii', $id, $otherId, $id);
        foreach ($userTO->getBlockedIds() as $otherId) {
            $stmt->execute();
        }
        $stmt->close();
        static::$link->commit();
    }

    function obtainUserTO(string $phoneNumber): UserTO {
        return $this->queryUsersTable('phone=?', 's', $phoneNumber);
    }

    function obtainUserTOById(int $id): UserTO {
        return $this->queryUsersTable('_id=?', 'i', $id);
    }

    private function queryUsersTable(string $whereClause, string $paramType, $param): UserTO {
        static::$link->begin_transaction();
        $stmt = static::$link->prepare('SELECT _id,phone,name,picture_id FROM Users WHERE ' . $whereClause . ' LIMIT 1');
        $stmt->bind_param($paramType, $param);
        $stmt->execute();
        $stmt->bind_result($id, $phone, $name, $picture_id);
        if (!$stmt->fetch()) {
            $stmt->close();
            static::$link->rollback();
            throw new UnableToGetTOException("Unable to get UserTO with param '$param'($paramType) and whereClause '$whereClause'.");
        }
        $stmt->close();
        $stmt = static::$link->prepare('SELECT blocked FROM Blocked WHERE blocker=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($blocked);
        $blockedIds = array();
        while ($stmt->fetch()) {
            $blockedIds[] = $blocked;
        }
        $stmt->close();

        static::$link->commit();

        return new UserTO($id, $phone, $name, $picture_id, $blockedIds, $this);
    }

    function createUser(string $phoneNumber) {
        static::$link->begin_transaction();
        $name = "New user";
        $pic = 0;
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $stmt = static::$link->prepare('INSERT INTO Users(phone,name,picture_id,last_update) VALUES(?,?,?,?)');
        $stmt->bind_param('ssis', $phoneNumber, $name, $pic, $now);
        $stmt->execute();
        $stmt->close();
        static::$link->commit();
    }

    /**
     * @param string[] $phones
     * @param null|string $lastUpdate
     * @return array|UserTO[]
     */
    function getExistentUsers(array $phones, $lastUpdate): array {
        static::$link->begin_transaction();
        if ($lastUpdate === null) {
            $stmt = static::$link->prepare('SELECT _id,phone,name,picture_id FROM Users WHERE phone=? LIMIT 1');
            $stmt->bind_param('s', $phoneNumber);
        } else {
            $stmt = static::$link->prepare('SELECT _id,phone,name,picture_id FROM Users WHERE phone=? AND last_update>=? LIMIT 1');
            $stmt->bind_param('ss', $phoneNumber, $lastUpdate);
        }
        $stmt->bind_result($id, $phone, $name, $picture_id);
        $usersPre = array();
        foreach ($phones as $phn) {
            $phoneNumber = $phn;
            $stmt->execute();
            if ($stmt->fetch()) {
                $usersPre[] = array($id, $phone, $name, $picture_id);
            }
        }
        $stmt->close();

        $stmt = static::$link->prepare('SELECT blocked FROM Blocked WHERE blocker=?');
        $stmt->bind_param('i', $id);
        $users = array();
        $stmt->bind_result($blocked);
        foreach ($usersPre as $user) {
            $id = $user[0];
            $stmt->execute();
            $blockedIds = array();
            while ($stmt->fetch()) {
                $blockedIds[] = $blocked;
            }
            $users[] = new UserTO($user[0], $user[1], $user[2], $user[3], $blockedIds, $this);
        }
        $stmt->close();
        static::$link->commit();

        return $users;
    }
}