#include <iostream>
#include <stdio.h>
#include <string.h>


using namespace std;

char* base_convert(unsigned long in,char* buf);
bool file_exists(char * filename);

char exception_message[100];
int err_code=0;

int main(int argc,char* argv[])
{
    // открываем основной файл на чтение
    FILE* main_handle=nullptr;
    FILE* handles[100];

    // файл - источник
    char src_file[1000];
    const char* default_src="/tmp/passports";
    strcpy(src_file,default_src);

    // место куда записываем файлы
    char dest_path[1000];
    const char* default_dest="/tmp/";
    strcpy(dest_path,default_dest);

    try{
        //анализ параметров
        if(argc>1){
            if(strcmp(argv[1],"--help")==0 || strcmp(argv[1],"-h")==0){
                cout << endl << "Использование:" <<endl
                << basename(argv[0]) << " --help|-h"<<endl
                << basename(argv[0]) << "[название файла-источника [путь для сохранения]]" << endl<<endl;

                return 0;
            }

            // указан файл. Проверим его существование
            if(!file_exists(argv[1])){
                sprintf(exception_message,"Файл %s не существует",argv[1]);
                throw exception_message;
            }
            // если существует, то...
            strcpy(src_file,argv[1]);

            // указан путь для сохранения. Проверим его существование
            if(argc>2) {
                strcpy(dest_path, argv[2]);
                if(dest_path[strlen(dest_path)-1]!='/') strcat(dest_path,"/");
            }
        }
        //анализ параметров завершен

        cout << "Открываем файл " << src_file << endl;
        main_handle=fopen(src_file,"r");

        if(main_handle== nullptr){
            sprintf(exception_message,"Не удалось открыть файл %s",src_file);
            throw exception_message;
        }

        // открываем 100 файлов
        cout << "Открываем пулл файлов для оптимизированного хранилища" << endl;
        int i;
        char tmp_filename[1000];
        for (i=0;i<100;i++){
            //sprintf(tmp_filename,"%02i")
            sprintf(tmp_filename,"%spassports%02i.tmp",dest_path,i);

            handles[i]=fopen(tmp_filename,"w");
            if(handles[i]==nullptr){
                sprintf(exception_message,"Не удалось открыть файл %s для записи",tmp_filename);
                throw exception_message;
            }
        }

        cout << "Обрабатываем" << endl;

        char buffer[1000];
        int id=0;
        int line=1;
        int bad=0; // количество плохих строк

        // первую строку надо пропистить, т.к. там заголовок
        fgets(buffer,999,main_handle);
        //cout << buffer << endl;

        while (fgets(buffer,999,main_handle)) {
            line++;
            //cout << buffer;

            if(buffer[0]=='\n' || buffer[0]=='\r')
                continue;

            id = (buffer[0] - '0') * 10 + (buffer[1] - '0');
            if (id < 0 || id > 100) {
                //cerr << "Некорректная строка " << line << ": " << buffer;
//                sprintf(exception_message, "Некорректная строка %i: %s", line,buffer);
//                throw exception_message;
                bad++;
                continue;
            }
            //cout << id << endl;

            if (!fputs(buffer, handles[id])) {
                sprintf(exception_message, "Ошибка записи в хранилище %i", id);
                throw exception_message;
            }

//            if(line>1000)
//                break;
        }

//cout <<"feof" << feof(main_handle) << endl;
        if(!feof(main_handle)){
            sprintf(exception_message,"Прерывание при разборе строки %i",line);
            throw exception_message;
        }

        cout <<"Обработано строк : "<< line << endl;
        if(bad) cout <<"Найдено некорректных : " << bad << endl;

        if(line<100000000){
            sprintf(exception_message,"Обработано %i строк. Это меньше чем необходимо. Похоже на сбой",line);
            throw exception_message;
        }

    } catch (char * e){
        cerr << e << endl;
        err_code=1;
    }

    // зачистка
    cout << endl << "Завершение"  << endl;
    if(main_handle) fclose(main_handle);
    for(int i=0;i<100;i++){
        if(handles[i]){
            fclose(handles[i]);
            handles[i]=nullptr;
        } else break;
    }

    if(err_code!=0){
        return err_code;
    }

/*
    unsigned long int num;
    char buf[10];

    cout << "Hello world!" << endl;




    cin >> num;
    cout << endl<<num;

    char* result;
    result=base_convert(num,buf);
    cout << endl << "result" <<"x" << result << endl;
    */

    cout << endl << "Готово"  << endl;
    return 0;

}

char* base_convert(unsigned long in,char* buf){
    static char digits[] = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYabcdefghijklmnopqrstuvwxy";
    //char buf[10];
    char* ptr;
    int base;

    base=sizeof(digits)-1;
    cout <<endl<<"base="<<base<<endl;

    ptr=buf+sizeof(buf)-1;
    *ptr='\0';

cout << endl <<"p=" << ptr << "in=" << in << endl;
	do {
cout <<"p=" << ptr << "in=" << in << endl;
		*--ptr = digits[in % base];
		in /= base;
	} while (in);
cout <<"p=" << ptr << "in=" << in << endl;

    return ptr;
}

/*static char digits[] = "0123456789abcdefghijklmnopqrstuvwxyz";
	char buf[(sizeof(zend_ulong) << 3) + 1];
	char *ptr, *end;
	zend_ulong value;

	if (Z_TYPE_P(arg) != IS_LONG || base < 2 || base > 36) {
		return ZSTR_EMPTY_ALLOC();
	}

	value = Z_LVAL_P(arg);

	end = ptr = buf + sizeof(buf) - 1;
	*ptr = '\0';

	do {
		ZEND_ASSERT(ptr > buf);
		*--ptr = digits[value % base];
		value /= base;
	} while (value);

return zend_string_init(ptr, end - ptr, 0);
*/

/**
 * Проверка существования файла
 * */
bool file_exists(char * filename){
    if (FILE *file = fopen(filename, "r")) {
        fclose(file);
        return true;
    }
    return false;
}