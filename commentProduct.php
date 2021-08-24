<?php

if (!defined('_PS_VERSION_'))
    return false;

require_once _PS_MODULE_DIR_ . ("commentProduct/commentProductClass.php");

class CommentProduct extends Module implements  \PrestaShop\PrestaShop\Core\Module\WidgetInterface
{

    private $templateFile;
    public function __construct()
    {
        $this->name = 'commentproduct';
        $this->author = 'Catalin Gobej Danut';
        $this->version = '1.0';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Product comment', array(), 'Modules.CommentProduct.Admin');
        $this->description = $this->trans('Permite clientilor sa lase un comentariu despre produs', array(), 'Modules.CommentProduct.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->templateFile ='module:commentProduct/views/templates/hook/CommentProduct.tpl';

    }

    public function renderWidget($hookName, array $configuration)
    {
        $this->smarty->assign($this->getWidgetVariables($hookName,  $configuration));
        return $this->fetch($this->templateFile);
    }
    public function install()
    {
        return parent::install()
                && $this->registerHook('displayFooterProduct')
                && $this->registerHook('displayHeader')
            && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS`'. _DB_PREFIX_ .'productComment`(
                    `id_comment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` int(10) NOT NULL,
                    `product_id` int(10) NOT NULL,
                    `comment` varchar(255) NOT NULL,
                    `active` BOOLEAN,
                    PRIMARY KEY (`id_comment`)                    
            ) ENGINE='._MYSQL_ENGINE_.'DEFAULT CHARSET=utf8;');

    }
    public function hookdisplayHeader(){
        $this->context->controller->registerStylesheet('modules-commentproduct', 'modules/'.$this->name.'/assets/style.css');
    }

    
    public function getContent(){
        $html=" ";
        if(Tools::isSubmit('getcsv')) {
            $folderName=_PS_UPLOAD_DIR_ ;
            $serverName= _PS_BASE_URL_ . __PS_BASE_URI__;
            $FileName= date('Y-m-d-i-s') . ".csv";
            $downloadFileUrl=$serverName.'upload/'. $FileName;
            $data=$this->getAllRecord();
            $file=fopen($folderName.$FileName,'w');
            fputcsv($file,array_keys($data[0]),';');

            foreach ($data as $item){
                fputcsv($file,$item,';');
            }
            fclose($file);
            $html="<a type=\"button\" class=\" btn btn-primary\" ' href='". $downloadFileUrl."'>Descarcare comentarii.csv</a>";
        }

        //configurare delete (stergere mesaj)
      if(Tools::getValue('id_comment'))
      {
          $resultAction=false;
        $id=Tools::getValue('id_comment');
        $comment=new commentProductClass($id);
          if( Tools::getValue('updatecommentproduct'))
          {                     
            $comment->active=true;
            if($comment->save())
            $resultAction=true;
          } 
          if( Tools::isSubmit('deletecommentproduct'))
          {
            if($comment->delete())
            $resultAction=true;
          }
          if($resultAction)
          $html.= "<div class='alert alert-success'>Comanda reusita</div>";
            else 
            $html.= "<div class='alert alert-error'>Comanda nereusita</div>";
       }
        $data= $this->getAllRecord();
        //creare helplist
        $helper=new HelperList();
        $helper->identifier ="id_comment";
        $helper->shopLinkType = null;
        $helper->actions = array('edit','delete');

        $helper->title=$this->displayName;
        $helper->table=$this->name;

        $helper->token=Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex= AdminController::$currentIndex. '&configure='. $this->name ;

        $html.= $helper->generateList($data , array(
            'id_comment'=> array(
                'title'=>"ID",
                'width'=>80,
                'serch'=>false,
                'orderby'=>false                                                                                                                                                                                                                                                                                                                                                                                                        
            ),
            'comment'=> array(
                'title'=>"Comentariu"
            )
            ));
      return $html;
    }

  public function uninstall()
  {
      if( parent::uninstall()&&
          Db::getInstance()-> execute ('DROP TABLE IF EXISTS `'._DB_PREFIX_.'productComment`'))
        return true;
      return false;
  }

    public function getWidgetVariables($hookName, array $configuration)
    {
        // form submision
        $message1='';
        $message='';
        if( Tools::isSubmit('comment')){
           if($this->context->customer->isLogged())
           {
                $commentProduct=new commentProductClass();
                $commentProduct->comment=Tools::getValue('comment');
                $commentProduct->product_id=Tools::getValue('id_product');
                $commentProduct->user_id=(int)$this->context->cookie->id_customer;
                $commentProduct->active=false;
                if($commentProduct->save())
                    $message='true';
                else
                    $message='false';
           }
           else
           {
           $message1='false';
           }
        }

        //Luam comentarii anterioare
        $sql=new DbQuery();
        $sql->select('*');
        $sql->from('productComment','pc');
        $sql->innerJoin('customer','c','pc.user_id=c.id_customer');
        $sql->where(' pc.active=1 && pc.product_id = ' . (int)Tools::getValue('id_product'));

       return array(
           'messageResult1'=>$message1,
           'messageResult'=>$message,
           'comments'=>Db::getInstance()->executeS($sql)
       );
    }
    protected function getAllRecord(){
        $sql=new DbQuery();
        $sql->select('*');
        $sql->from('productComment','pc');
        $sql->innerJoin('customer','c','pc.user_id=c.id_customer');
        return Db::getInstance()->executeS($sql);
    }
}