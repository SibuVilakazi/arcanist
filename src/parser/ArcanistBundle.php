  private $author;

  public function setAuthor($author) {
    $this->author = $author;
    return $this;
  }
  public function getAuthor() {
    return $this->author;
  }
      $author        = idx($meta_info, 'author');
      $author        = null;
      'version'      => 4,
      'author'       => $this->getAuthor(),
          $type == ArcanistDiffChangeType::TYPE_COPY_AWAY ||
          $type == ArcanistDiffChangeType::TYPE_CHANGE) {
        $old_phid = $old_binary->getMetadata('old:binary-phid');