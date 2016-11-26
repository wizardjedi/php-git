<?php

class GitRepo {
    protected $path;

    function getPath() {
        return $this->path;
    }

    function setPath($path) {
        $this->path = $path;
    }

    function getBranches() {
        $glob = scandir($this->path."refs/heads/");

        $result = array();

        foreach ($glob as $name) {
            if ($name != "." && $name != "..") {
                $result[] = new GitBranch($this, $name);
            }
        }

        return $result;
    }
}

class GitBranch {
    /**
     *
     * @var GitRepo
     */
    protected $repo;

    protected $name;

    function getRepo() {
        return $this->repo;
    }

    function getName() {
        return $this->name;
    }

    function setRepo($repo) {
        $this->repo = $repo;
    }

    function setName($name) {
        $this->name = $name;
    }

    function __construct($repo, $name) {
        $this->repo = $repo;
        $this->name = $name;
    }

    function getCommits() {
        $hash = trim(file_get_contents($this->getRepo()->getPath()."refs/heads/".$this->getName()));

        $commit = $this->getCommit($hash);

        $result = array();

        do {
            $result[] = $commit;

            $commit = $commit->getParent();
        } while ($commit != null);

        return $result;
    }

    function getCommit($hash) {
        return new GitCommit($this->getRepo(), $this, $hash);
    }
}

class GitCommit {
    /**
     *
     * @var GitRepo
     */
    protected $repo;

    /**
     *
     * @var GitBranch
     */
    protected $branch;

    protected $id;

    protected $parent;

    protected $tree;

    protected $message;

    protected $author;

    function __construct(GitRepo $repo, GitBranch $branch, $id) {
        $this->repo = $repo;
        $this->branch = $branch;
        $this->id = $id;

        $this->build();
    }

    function build() {
        $prefix = substr($this->id, 0, 2);
        $suffix = substr($this->id, 2);

        $rawContent = file_get_contents($this->getRepo()->getPath()."objects/${prefix}/${suffix}");

        $content = trim(gzuncompress($rawContent));

        list($str, $commitContent) = explode("\00", $content);

        list($data, $description) = explode("\n\n", $commitContent, 2);

        $this->message = $description;

        $fields = explode("\n", $data);

        foreach ($fields as $field) {
            list($name, $rest) = explode(" ", $field, 2);

            switch ($name) {
                case "author":
                    $this->setAuthor($rest);
                    break;
                case "parent":
                    $this->setParent(new GitCommit($this->getRepo(), $this->getBranch(), $rest));
                    break;
                case "tree":
                    $this->setTree(new GitTree($this->getRepo(), $this->getBranch(), $this, $rest));
                    break;
            }
        }
    }

    function getTree() {
        return $this->tree;
    }

    function getMessage() {
        return $this->message;
    }

    function getAuthor() {
        return $this->author;
    }

    function setTree($tree) {
        $this->tree = $tree;
    }

    function setMessage($message) {
        $this->message = $message;
    }

    function setAuthor($author) {
        $this->author = $author;
    }

    function getRepo() {
        return $this->repo;
    }

    function getBranch() {
        return $this->branch;
    }

    function getId() {
        return $this->id;
    }

    function getParent() {
        return $this->parent;
    }

    function setRepo(GitRepo $repo) {
        $this->repo = $repo;
    }

    function setBranch(GitBranch $branch) {
        $this->branch = $branch;
    }

    function setId($id) {
        $this->id = $id;
    }

    function setParent($parent) {
        $this->parent = $parent;
    }
}

class GitTree {
    /**
     *
     * @var GitRepo
     */
    protected $repo;

    /**
     *
     * @var GitBranch
     */
    protected $branch;

    /**
     *
     * @var GitCommit
     */
    protected $commit;

    protected $id;

    protected $files;

    function getRepo() {
        return $this->repo;
    }

    function getBranch() {
        return $this->branch;
    }

    function getCommit() {
        return $this->commit;
    }

    function setRepo(GitRepo $repo) {
        $this->repo = $repo;
    }

    function setBranch(GitBranch $branch) {
        $this->branch = $branch;
    }

    function setCommit(GitCommit $commit) {
        $this->commit = $commit;
    }

    function getFiles() {
        return $this->files;
    }

    function __construct(GitRepo $repo, GitBranch $branch, GitCommit $commit, $id) {
        $this->repo = $repo;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->id = $id;

        $this->build();
    }

    function build() {
        $prefix = substr($this->id, 0, 2);
        $suffix = substr($this->id, 2);

        $rawContent = file_get_contents($this->getRepo()->getPath()."objects/${prefix}/${suffix}");

        $content = trim(gzuncompress($rawContent));

        list($header, $content) = explode("\0", $content,2);

        $offset = 0;

        $this->files = array();

        $len = strlen($content);

        do {
            $nulPos = strpos($content, "\0", $offset);

            if ($nulPos !== false) {
                $filePart = substr($content, $offset, $nulPos - $offset);

                list($mode, $fileName) = explode(" ", $filePart, 2);

                $hash = bin2hex(substr($content, $nulPos + 1, 20));

                $offset = $nulPos + 20;

                $file = new GitFile($this->getRepo(), $this->getBranch(), $this->getCommit(), $this, $fileName, $hash);

                $this->files[] = $file;
            }
        } while ($nulPos !== false && $offset < $len);
    }
}

class GitFile {
    /**
     *
     * @var GitRepo
     */
    protected $repo;

    /**
     *
     * @var GitBranch
     */
    protected $branch;

    /**
     *
     * @var GitCommit
     */
    protected $commit;

    /**
     *
     * @var GitTree
     */
    protected $tree;

    protected $fileName;

    protected $id;

    function __construct(GitRepo $repo, GitBranch $branch, GitCommit $commit, GitTree $tree, $fileName, $id) {
        $this->repo = $repo;
        $this->branch = $branch;
        $this->commit = $commit;
        $this->tree = $tree;
        $this->fileName = $fileName;
        $this->id = $id;
    }

    public function getContent() {
        $prefix = substr($this->id, 0, 2);
        $suffix = substr($this->id, 2);

        $path = $this->getRepo()->getPath()."objects/${prefix}/${suffix}";

        $rawContent = file_get_contents($path);

        $content = trim(gzuncompress($rawContent));

        list($header, $fileContent) = explode("\0", $content,2);

        return $fileContent;
    }


    function getRepo() {
        return $this->repo;
    }

    function getBranch() {
        return $this->branch;
    }

    function getCommit() {
        return $this->commit;
    }

    function getTree() {
        return $this->tree;
    }

    function getFileName() {
        return $this->fileName;
    }

    function getId() {
        return $this->id;
    }

    function setRepo(GitRepo $repo) {
        $this->repo = $repo;
    }

    function setBranch(GitBranch $branch) {
        $this->branch = $branch;
    }

    function setCommit(GitCommit $commit) {
        $this->commit = $commit;
    }

    function setTree(GitTree $tree) {
        $this->tree = $tree;
    }

    function setFileName($fileName) {
        $this->fileName = $fileName;
    }

    function setId($id) {
        $this->id = $id;
    }
}

$gitRepo = new GitRepo();

$gitRepo->setPath("../data/test-prj/");

$workLine = "digraph {";

foreach ($gitRepo->getBranches() as $branch) {
    echo "<hr />";

    echo "Branch <b>".$branch->getName()."</b><br />\n";

    $commits = $branch->getCommits();

    echo "<table border='1'>";

    foreach ($commits as $commit) {
        if ($commit->getParent() != null) {
            $workLine .= "L".$commit->getId()." -> L".$commit->getParent()->getId()."[label=\"".$commit->getMessage()."\"];\n";
        }

        echo "<tr style='background: green;'><td>".$commit->getId()."</td><td>".$commit->getAuthor()."</td></tr>";
        echo "<tr style='background: orange;'><td colspan='2'><code>".$commit->getMessage()."</code></td></tr>";
        echo "<tr><td colspan='2'>";

        $tree = $commit->getTree();

        if ($tree != null) {
            foreach ($tree->getFiles() as $gitFile) {
                echo "<table border=1>";

                echo "<tr><td>".$gitFile->getFileName()."</td>";
                echo "<tr style='background:silver'><td><code><pre>".$gitFile->getContent()."</pre></code></td>";

                echo "</table>";
            }
        }

        echo "</td></tr>";
    }

    echo "</table>";
}

$workLine .= "}";


file_put_contents("1.dot", $workLine);

exec("/usr/bin/dot -Tpng -o1.png 1.dot");

echo "<img src='1.png?".time()."' />";