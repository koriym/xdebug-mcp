<?php

class Node {
    public $value;
    public $parent = null;
    public $children = [];
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function addChild($child) {
        $child->parent = $this;              // Line 13 - First breakpoint (循環参照作成)
        $this->children[] = $child;
    }
}

class CircularReferenceDemo {
    public $self;
    public $data;
    
    public function __construct() {
        $this->self = $this;                 // Line 22 - Second breakpoint (自己参照)
        $this->data = [
            'server' => $_SERVER,            // Line 24 - Third breakpoint (巨大な配列)
            'globals' => $GLOBALS,           // Line 25 - Fourth breakpoint (再帰参照)
            'nested' => []
        ];
    }
    
    public function createNestedStructure() {
        $a = ['name' => 'a'];
        $b = ['name' => 'b'];
        $c = ['name' => 'c'];
        
        $a['ref_to_b'] = &$b;                // Line 35 - Fifth breakpoint (参照)
        $b['ref_to_c'] = &$c;
        $c['ref_to_a'] = &$a;                // Line 37 - Sixth breakpoint (循環参照完成)
        
        $this->data['nested'] = $a;          // Line 39 - Seventh breakpoint
        
        return $a;                           // Line 41 - Eighth breakpoint
    }
    
    public function accessGlobals() {
        global $recursiveTest;
        $recursiveTest = $this;              // Line 46 - Ninth breakpoint (グローバルに自己参照)
        
        $complexArray = [
            'self_ref' => $this,
            'global_ref' => $GLOBALS,        // Line 50 - Tenth breakpoint (参照削除)
            'server_subset' => [
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'localhost',
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'all_server' => $_SERVER         // Line 54 - Eleventh breakpoint
            ]
        ];
        
        return $complexArray;                // Line 58 - Twelfth breakpoint
    }
}

function demonstrateNodeTree() {
    $root = new Node('root');                // Line 63 - Thirteenth breakpoint
    $child1 = new Node('child1');
    $child2 = new Node('child2'); 
    $grandchild = new Node('grandchild');
    
    $root->addChild($child1);                // Line 68 - Fourteenth breakpoint
    $root->addChild($child2);
    $child1->addChild($grandchild);          // Line 70 - Fifteenth breakpoint
    
    // 循環参照を作成（危険！）
    $grandchild->addChild($root);            // Line 73 - Sixteenth breakpoint (循環！)
    
    return $root;                            // Line 75 - Seventeenth breakpoint
}

function main() {
    echo "🔄 Testing recursive references...\n";
    
    $demo = new CircularReferenceDemo();     // Line 81 - Eighteenth breakpoint
    $nested = $demo->createNestedStructure();
    $globals = $demo->accessGlobals();
    $tree = demonstrateNodeTree();
    
    echo "📊 Recursive structures created\n";
    echo "⚠️  Warning: These contain circular references!\n";
    
    // 通常のprint_rやvar_dumpは無限ループになる危険
    // echo "Tree structure:\n";
    // print_r($tree); // これは危険！
    
    return [                                 // Line 92 - Nineteenth breakpoint
        'demo' => $demo,
        'nested' => $nested,
        'globals' => $globals,
        'tree' => $tree
    ];
}

main();                                      // Line 99 - Twentieth breakpoint
?>