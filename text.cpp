#include <iostream>
#include <fstream>
#include <string>
#include<locale>
#include "cryptopp/cryptlib.h"
#include "cryptopp/rijndael.h"
#include "cryptopp/modes.h"
#include "cryptopp/ccm.h"
#include "cryptopp/gcm.h"
#include "cryptopp/xts.h"
#include "cryptopp/files.h"
#include "cryptopp/osrng.h"
#include "cryptopp/hex.h"
#include "cryptopp/filters.h"

using namespace CryptoPP;
using namespace std;

#define TAG_SIZE 12

// Chọn chế độ mã hóa
string SelectMode()
{
    int choice;
    while (true)
    {
        cout << "Select mode: " << endl;
        cout << "1. ECB\n2. CBC\n3. OFB\n4. CFB\n5. CTR\n6. XTS\n7. CCM\n8. GCM\n";
        cout << "Enter choice: ";
        cin >> choice;
        if (choice >= 1 && choice <= 8)
        {
            switch (choice)
            {
            case 1: return "ECB";
            case 2: return "CBC";
            case 3: return "OFB";
            case 4: return "CFB";
            case 5: return "CTR";
            case 6: return "XTS";
            case 7: return "CCM";
            case 8: return "GCM";
            }
        }
        else cout << "Invalid choice, please try again.\n";
    }
}

std::string SecByteBlockToHex(const SecByteBlock &block)
{
    std::string encoded;
    StringSource ss(block, block.size(), true,
                    new HexEncoder(new StringSink(encoded)));
    return encoded;
}

// Sinh Key và IV
pair<SecByteBlock, SecByteBlock> Key_Generation(string mode)
{
    AutoSeededRandomPool prng;
    SecByteBlock key((mode == "XTS") ? AES::DEFAULT_KEYLENGTH * 2 : AES::DEFAULT_KEYLENGTH);
    SecByteBlock iv(AES::BLOCKSIZE);
    prng.GenerateBlock(key, key.size());
    prng.GenerateBlock(iv, iv.size());
    return make_pair(key, iv);
}

// Hàm mã hóa
string Encrypt(const string& mode, const SecByteBlock &key, const SecByteBlock &iv, const string &plain)
{
    string cipher;
    try
    {
        if (mode == "ECB")
        {
            ECB_Mode<AES>::Encryption e;
            e.SetKey(key, key.size());
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
        else if (mode == "CBC")
        {
            CBC_Mode<AES>::Encryption e;
            e.SetKeyWithIV(key, key.size(), iv);
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
        else if (mode == "OFB")
        {
            OFB_Mode<AES>::Encryption e;
            e.SetKeyWithIV(key, key.size(), iv);
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
        else if (mode == "CFB")
        {
            CFB_Mode<AES>::Encryption e;
            e.SetKeyWithIV(key, key.size(), iv);
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
        else if (mode == "CTR")
        {
            CTR_Mode<AES>::Encryption e;
            e.SetKeyWithIV(key, key.size(), iv);
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
        else if (mode == "XTS") {
            XTS_Mode<AES>::Encryption e;
            e.SetKeyWithIV(key, key.size(), iv);
            StringSource(plain, true, new StreamTransformationFilter(e, new StringSink(cipher)));
        }
    }
    catch (const Exception &e)
    {
        cerr << "Encryption failed: " << e.what() << endl;
    }
    string encoded;
    StringSource ss2(cipher, true,
        new HexEncoder(
            new StringSink(encoded)) );
        return encoded;
}

// Hàm giải mã
string Decrypt(const string& mode, const SecByteBlock &key, const SecByteBlock &iv, const string &cipher)
{
    string recovered;
    try
    {
        if (mode == "ECB")
        {
            ECB_Mode<AES>::Decryption d;
            d.SetKey(key, key.size());
            StringSource(cipher, true, new StreamTransformationFilter(d, new StringSink(recovered)));
        }
        else if (mode == "CBC")
        {
            CBC_Mode<AES>::Decryption d;
            d.SetKeyWithIV(key, key.size(), iv);
            StringSource(cipher, true, new StreamTransformationFilter(d, new StringSink(recovered)));
        }
        else if (mode == "OFB")
        {
            OFB_Mode<AES>::Decryption d;
            d.SetKeyWithIV(key, key.size(), iv);
            StringSource(cipher, true, new StreamTransformationFilter(d, new StringSink(recovered)));
        }
        else if (mode == "CFB")
        {
            CFB_Mode<AES>::Decryption d;
            d.SetKeyWithIV(key, key.size(), iv);
            StringSource(cipher, true, new StreamTransformationFilter(d, new StringSink(recovered)));
        }
        else if (mode == "CTR")
        {
            CTR_Mode<AES>::Decryption d;
            d.SetKeyWithIV(key, key.size(), iv);
            StringSource(cipher, true, new StreamTransformationFilter(d, new StringSink(recovered)));
        }
        
    }
    catch (const Exception &)
    {
        cerr << "Decryption failed! Invalid ciphertext." << endl;
        return "";
    }
    return recovered;
}

int main()
{
    std::locale::global(std::locale("C.utf8"));
    int action;
    cout << "Choose action:\n1. Encrypt\n2. Decrypt\nEnter choice: ";
    cin >> action;
    cin.ignore();
    string mode = SelectMode();

    if (action == 1) // Mã hóa
    {
        auto key_iv = Key_Generation(mode);
        SecByteBlock key = key_iv.first;
        SecByteBlock iv = key_iv.second;
        
        string plain, cipher;
        cout << "Enter plaintext: ";
        cin.ignore();
        getline(cin, plain);
        cout << "Key: "<< SecByteBlockToHex(key)<< endl;
        cout << "iv: "<< SecByteBlockToHex(iv)<< endl;
        cipher = Encrypt(mode, key, iv, plain);
        cout << "Cipher: " << cipher<< endl;
        ofstream keyFile("key_iv.txt");
        keyFile << SecByteBlockToHex(key) << endl;
        keyFile << SecByteBlockToHex(iv) << endl;
        keyFile.close();
        ofstream ouputFile("ouput.txt");
        ouputFile<< cipher<<endl;
        ouputFile.close();
    }
    else if (action == 2) // Giải mã
    {
        int a;
        string key_hex, iv_hex, cipher_hex;
        ifstream keyFile("key_iv.txt");
        getline(keyFile, key_hex);
        getline(keyFile, iv_hex);
        keyFile.close();
        cout <<"Choose action:\n1. screen\n2. file\nEnter choice: ";
        cin >> a;
        if (a == 1){
        cout << "Enter ciphertext (Hex): ";
        cin >> cipher_hex;
        }
        else if (a == 2){
            ifstream ouputFile("ouput.txt");
            getline(ouputFile, cipher_hex);
        }
        string decoded;
        StringSource(cipher_hex, true, new HexDecoder(new StringSink(decoded)));
        SecByteBlock key(AES::DEFAULT_KEYLENGTH);
                StringSource ssKey(key_hex, true,
                                   new HexDecoder(new ArraySink(key, key.size())));
                SecByteBlock iv(AES::BLOCKSIZE);
                StringSource ssIV(iv_hex, true,
                                  new HexDecoder(new ArraySink(iv, iv.size())));
        string recovered = Decrypt(mode, key, iv, decoded);
        if (!recovered.empty()) cout << "Recovered text: " << recovered << endl;
    }
    return 0;
}
