<?php
/**
 * controllers/PostsController.php  
 */  
class ProductsController extends Zend_Controller_Action  
{  

  public function init() // called always before actions  
  {  
      $this->Products = new Products(); // DbTable 
      $this->ProductsCategories = new ProductsCategories(); 
  }
  public function addAction()  
  {  
      //$ProductsCategories = new ProductsCategories();
      $form = $this->getForm(); // getting the post form

      if ($this->getRequest()->isPost()) { //is it a post request ?  
          $postData = $this->getRequest()->getPost(); // getting the $_POST data  
          if ($form->isValid($postData)) {  
              $formData = $form->getValues(); // data filtered  

              $formData['pro_price'] = str_replace(',','.',ltrim($formData['pro_price'],'R$ ')); 
            
              //categories from form
              $categories = $formData['category_id'];

              //removing categories id from products
              unset($formData['category_id']);  
              
              $formData += array('pro_created_at' => date('Y-m-d H:i:s'), 'pro_updated_at' => date('Y-m-d H:i:s'));  
              $this->Products->insert($formData); // database insertion

              //taking the id of the last product created 
              $lastInsertId = $this->Products->getAdapter()->lastInsertId();

              //inserting the products-categories values
              foreach($categories as $category) {
                $productsCategoriesData = [
                    'product_id' => $lastInsertId,
                    'category_id' => $category
                ];
                $this->ProductsCategories->insert($productsCategoriesData);
              }
              
              $this->_redirect('/products/index'); 
          }  
          else $form->populate($postData); // show errors and populate form with $postData  
      }
      $this->view->form = $form; // assigning the form to view  
  }
  public function indexAction()  
  {  
      // get all posts - the newer first
      #########################################################  
      $select = $this->Products->select()
                               ->from('products',['pro_id','pro_name','pro_price','pro_description','pro_image','pro_created_at','pro_updated_at'])
                               ->joinLeft('products_categories','products.pro_id = products_categories.product_id', 'category_id')
                               ->joinLeft('categories','products_categories.category_id = categories.cat_id', array('cat_name' => new Zend_Db_Expr('GROUP_CONCAT(categories.cat_name)')))
                               ->group('products.pro_id')
                               ->order('pro_id desc')
                               ->setIntegrityCheck(false);

         
    $this->view->products = $this->Products->fetchAll($select);
    ############################################################
    // $stmt = $select->query();
    // $result = $stmt->fetchAll();
    //$this->view->products = $this->Products->fetchAll(null, 'pro_id desc');
  }
///////////////////////////
  public function showAction()  
  {  
      $id = $this->getRequest()->getParam('id');  
      if ($id > 0) {  
          $product = $this->Products->find($id)->current(); // or $this->Products->fetchRow("id = $id");  
          $this->view->product = $product;  
      }  
      else $this->view->message = 'The post ID does not exist';  
  }
  public function editAction()  
  {  
      $form = $this->getForm();

      $id = $this->getRequest()->getParam('id');
      if ($id > 0) {  
          if ($this->getRequest()->isPost()) { // update form submit  
              $postData = $this->getRequest()->getPost();  
              if ($form->isValid($postData)) {  
                  $formData = $form->getValues();
                  $formData['pro_price'] = str_replace(',','.',ltrim($formData['pro_price'],'R$ '));
                  unset($formData['category_id']);  
                  $formData += array('pro_updated_at' => date('Y-m-d H:i:s'));  
                  $this->Products->update($formData, "pro_id = $id"); // update  
                  $this->_redirect('/products/index');
              }  
              else $form->populate($postData);  
          }  
          else {  
              $post = $this->Products->find($id)->current();  
              $form->populate($post->toArray()); // populate method parameter has to be an array
              // add the id hidden field in the form  
              $hidden = new Zend_Form_Element_Hidden('id');  
              $hidden->setValue($id);
              $form->addElement($hidden);  
          }  
      }  
      else $this->view->message = 'The post ID does not exist';
      //remove the category of form
      $form->removeElement('category_id');
      $this->view->form = $form; 
  }
  public function delAction()  
  {  
      $id = $this->getRequest()->getParam('id');  
      if ($id > 0) {  
          // option 1  
          /*$post = $this->Posts->find($id)->current();  
          $post->delete();*/
          // option 2  
          $this->ProductsCategories->delete("product_id = $id");
          $this->Products->delete("pro_id = $id");
          $this->_redirect('/products/index');  
      }  
  }


  //////////////////////////////
  public function getForm()  
  {  
      $Categories = new Categories();
      $getCategories = $Categories->fetchAll(null,'cat_id desc');
      $categories= [];
      foreach($getCategories as $getCategory) {
        $categories[$getCategory->cat_id] = $getCategory->cat_name;
      }

      #####################test#############################
      $category = new Zend_Form_Element_MultiCheckbox('category_id[]');
      $category->setLabel('Category')
                ->addMultiOptions($categories)
                ->setAttrib('class','form-check-input');

      $name = new Zend_Form_Element_Text('pro_name');  
      $name->setLabel('Name')  
          ->setDescription('Just put the product name here')  
          ->setRequired(true) // required field  
          ->addValidator('StringLength', false, array(10, 120)) // min 10 max 120  
          ->addFilters(array('StringTrim'))
          ->setAttrib('class','form-control');

      $price = new Zend_Form_Element_Text('pro_price');  
      $price->setLabel('Price')
          ->setDescription('Just put the product price here')  
          ->setRequired(true)
          ->setAttrib('class','form-control')
          ->setAttrib('id','price');  

      $description = new Zend_Form_Element_Textarea('pro_description');  
      $description->setLabel('Description')  
          ->setRequired(true)  
          ->setDescription('Product Description')
          ->addFilters(array('HtmlEntities'))
          ->setAttrib('class','form-control'); // remove HTML tags

      $image = new Zend_Form_Element_File('pro_image');  
      $image->setLabel('Image')  
          //->setRequired(true)  
          ->setDescription('Product Image')
          ->addFilters(array('HtmlEntities'))
          ->setDestination(PUBLIC_PATH.'/image')
          ->setAttrib('class','form-control'); // remove HTML tags

      $submit = new Zend_Form_Element_Submit('submit');  
      $submit->setLabel('submit') // the button's value  
          ->setIgnore(true)
          ->setAttrib('class','btn btn-primary mb-3'); // very usefull -> it will be ignored before insertion
      $form = new Zend_Form();  
      $form->addElements(array($name,$price, $category, $description, $image, $submit))
            ->setMethod('post')
            ->setAction('');  
          // ->setAction('') // you can set your action. We will let blank, to send the request to the same action
      return $form; // return the form  
  }
  
}