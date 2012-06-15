<?php

  /**
   *
   * @package    symfony
   * @subpackage plugin
   * @author     Lionel Guichard <lionel.guichard@gmail.com>
   */
  class sfSocialGuardUser extends PluginsfGuardUser
  {
    protected $contacts = null;

    /**
     * Check if user has contact.
     * @param  sfGuardUser $userTo
     * @return boolean
     */
    public function hasContact (sfGuardUser $userTo)
    {
      $q = Doctrine_Query::create()
        ->from('sfSocialContact c')
        ->where('c.user_from = ?', $this->getId())
        ->andWhere('c.user_to = ?', $userTo->getId());

      return $q->count() == 1;
    }

    /**
     * Check if user has a contact request.
     * @param  sfSocialGuardUser $userTo
     * @return boolean
     */
    public function hasContactRequest (sfSocialGuardUser $userTo)
    {
      $q = Doctrine_Query::create()
        ->from('sfSocialContactRequest r')
        ->where('r.user_from = ?', $this->getId())
        ->andWhere('r.user_to = ?', $userTo->getId());

      return $q->count() == 1;
    }

    /**
     * Send request to contact.
     * @param  sfGuardUser $userTo
     * @param  string      $message
     * @return boolean
     */
    public function sendRequestContact (sfGuardUser $userTo, $message = '')
    {
      if ($userTo->getId() == $this->getId())
      {
        throw new Exception(sprintf('You can\'t add yourself as a contact', $userTo));
      }

      if ($this->hasContact($userTo))
      {
        throw new Exception(sprintf('You can\'t add a contact that already exist', $userTo));
      }

      $contactRequest = new sfSocialContactRequest();
      $contactRequest->setUserFrom($this->getId());
      $contactRequest->setUserTo($userTo->getId());
      $contactRequest->setMessage($message);
      $contactRequest->save();
    }

    /**
     * Accept request from contact.
     * @param  sfSocialContactRequest $contactRequest
     * @return boolean
     */
    public function acceptRequestContact (sfSocialContactRequest $contactRequest)
    {
      // add contact
      $this->addContact($contactRequest->getFrom());

      // mark as accept
      return $contactRequest->accepte();
    }

    /**
     * Refuse request from contact.
     * @param  sfSocialContactRequest $contactRequest
     * @return boolean
     */
    public function denyRequestContact (sfSocialContactRequest $contactRequest)
    {
      return $contactRequest->refuse();
    }

    /**
     * Number of contacts.
     * @return integer
     */
    public function countContacts ()
    {
      return Doctrine_Query::create()
          ->from('sfSocialContact c')
          ->where('c.user_from = ?', $this->getId())
          ->count();
    }

    /**
     * Get contacts
     * @param  integer $limit
     * @return array
     */
    public function getContacts ($limit = 0)
    {
      if ( ! is_array($this->contacts))
      {
        $this->contacts = array();
        $q = Doctrine_Query::create()
          ->from('sfSocialContact c')
          ->where('c.user_from = ?', $this->getId());
        if ($limit > 0)
        {
          $q->limit($limit);
        }
        $contacts = $q->execute();
        if ( ! empty($contacts))
        {
          foreach ($contacts as $contact)
          {
            $this->contacts[] = $contact->getTo();
          }
        }
      }

      return $this->contacts;
    }

    /**
     * Get a pager of contacts
     * @param  integer $page current page
     * @return sfPager
     */
    public function getContactsPager ($page = 1)
    {
      return Doctrine::getTable('sfSocialContact')->getContacts($this, $page);
    }

    /**
     * Get contact list, plus a sender user (if not already in contacts)
     * This is useful to reply to a message (see sfSocialMessageForm)
     * @param  integer $senderId
     * @return array
     */
    public function getContactsAndSender ($senderId)
    {
      $contacts = $this->getContacts();
      $sender = Doctrine::getTable('sfGuardUser')->find($senderId);
      if (empty($sender) || in_array($sender, $contacts))
      {
        return $contacts;
      }
      $contacts[] = $sender;

      return $contacts;
    }

    /**
     * Add contact
     * @param sfGuardUser $userTo
     */
    public function addContact (sfGuardUser $userTo)
    {
      $contact = new sfSocialContact();
      $contact->setFrom($this);
      $contact->setTo($userTo);
      $contact->save();
      $contact = new sfSocialContact();
      $contact->setFrom($userTo);
      $contact->setTo($this);
      $contact->save();
    }

    /**
     * Remove contact
     * TODO delete single objects
     * @param sfGuardUser $userTo
     */
    public function removeContact (sfGuardUser $userTo)
    {
      Doctrine_Query::create()
        ->delete('sfSocialContact c')
        ->where('c.user_from = ? and c.user_to = ?', array($this->getId(), $userTo->getId()));
      Doctrine_Query::create()
        ->delete('sfSocialContact c')
        ->where('c.user_to = ? and c.user_from = ?', array($this->getId(), $userTo->getId()));
    }

    /**
     * Add contact by username
     * @param string $username
     */
    public function addContactbyUsername ($username)
    {
      $userTo = Doctrine::getTable('sfGuardUser')->retrieveByUsername($username);
      if (empty($userTo))
      {
        throw new Exception(sprintf('The user "%s" does not exist.', $userTo));
      }
      $this->addContact($userTo);
    }

    /**
     * Remove contact by username
     * @param  string  $username
     */
    public function removeContactByUsername ($username)
    {
      $userTo = Doctrine::getTable('sfGuardUser')->retrieveByUsername($username);
      if (empty($userTo))
      {
        throw new Exception(sprintf('The user "%s" does not exist.', $userTo));
      }
      $this->removeContact($userTo);
    }

    /**
     * Remove all contatcs
     * TODO delete single objects
     */
    public function removeAllContacts ()
    {
      Doctrine_Query::create()
        ->delete('sfSocialContact c')
        ->where('c.user_from = ?', $this->getId());
      Doctrine_Query::create()
        ->delete('sfSocialContact c')
        ->where('c.user_to = ?', $this->getId());
    }

    /**
     * Get thumbnail picture path
     * @return string
     */
    public function getThumb ()
    {
      $path = sfConfig::get('app_sf_social_pic_path', '/sf_social_pics/');
      $pic = $this->getProfile()->getPicture();
      if (empty($pic))
      {
        return sfConfig::get('app_sf_social_default_pic', '/sfSocialPlugin/images/default.jpg');
        ;
      }
      else
      {
        $upload = substr(sfConfig::get('sf_upload_dir'), strlen(sfConfig::get('sf_web_dir')));

        return $upload . $path . 'thumbnails/' . $pic;
      }
    }

    /**
     * Get contacts shared with an user
     * @param  sfSocialGuardUser   $user
     * @param  integer             $limit
     * @return Doctrine_Collection
     */
    public function getSharedContacts (sfSocialGuardUser $user, $limit = 0)
    {
      return Doctrine::getTable('sfSocialContact')->getSharedContacts($this, $user, $limit);
    }

    /**
     * Number of shared contacts with an user
     * @param   sfSocialGuardUser $user
     * @return integer
     */
    public function countSharedContacts (sfSocialGuardUser $user)
    {
      return Doctrine::getTable('sfSocialContact')->countSharedContacts($this, $user);
    }

    /**
     * Get notifies (only unread ones)
     * @return Doctrine_Collection
     */
    public function getNotifies ()
    {
      return Doctrine_Query::create()
          ->from('sfSocialNotify n')
          ->leftJoin('n.user u')
          ->where('n.is_read = 0')
          ->execute();
    }

    /**
     * get ids of contacts
     * @return array
     */
    public function getContactIds ()
    {
      return Doctrine::getTable('sfSocialContact')->getContactIds($this);
    }

    /**
     * get related profile
     * @return sfGuardUserProfile
     */
    public function getProfile ()
    {
      $p = Doctrine_Query::create()
        ->from('sfGuardUserProfile p')
        ->where('p.user_id = ?', $this->getId())
        ->fetchOne();

      return empty($p) ? new sfGuardUserProfile : $p;
    }

  }